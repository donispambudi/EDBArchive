#include "../common/edbarchive.hpp"
#include "../common/json.hpp"

#include <cstddef>
#include <filesystem>
#include <fstream>
#include <iostream>
#include <string>

#include <vector>
#include <unordered_map>

#include <mariadb/conncpp.hpp>

#include "openfhe.h"
#include "cryptocontext-ser.h"
#include "key/key-ser.h"
#include "ciphertext-ser.h"
#include "scheme/bfvrns/bfvrns-ser.h"

namespace fs = std::filesystem;
using Json = nlohmann::json;

int main(int argc, char* argv[]) {
    const char* wrapper_flag = std::getenv("RUN_FROM_WRAPPER");
    if(wrapper_flag == nullptr || std::string(wrapper_flag) != "1") {
        std::cerr << "Command cannot be executed directly, run it from wrapper\n";
        return edbarchive::ExitCode::GeneralError;
    }

    // Parameters are validated by the Bash wrapper before invoking this executable.

    edbarchive::BackendArgs args;
    args.workspace_path = argv[1];
    args.job_id = argv[2];
    args.db_config_path = argv[3];

    std::cout << "Received Parameters:\n";
    std::cout << "   ├── Job ID = " << args.job_id << '\n';
    std::cout << "   ├── Workspace Path = " << args.workspace_path << "\n";
    std::cout << "   └── DB Config File Path = " << args.db_config_path << "\n\n";

    fs::path workspace_path = fs::path(args.workspace_path);
    if (!fs::is_directory(workspace_path)) {
        std::cerr << "Invalid workspace path\n";
        return edbarchive::ExitCode::InvalidParameter;
    }

    // load json config
    fs::path db_config_path = args.db_config_path;
    std::ifstream db_config_file(db_config_path);
    if (!db_config_file.is_open()) {
        std::cerr << "Failed to open DB config file: " << db_config_path << '\n';
        return edbarchive::ExitCode::ConfigFileError;
    }

    Json db_config_json;
    try {
        db_config_file >> db_config_json;
    }
    catch (const Json::parse_error& error) {
        std::cerr << "Invalid JSON in DB config file: " << error.what() << '\n';
        return edbarchive::ExitCode::InvalidConfigJson;
    }

    // load json job_config
    fs::path job_config_path = workspace_path / edbarchive::PathJobsDirectory / args.job_id / edbarchive::PathConfigFile;
    std::ifstream job_config_file(job_config_path);
    if (!job_config_file.is_open()) {
        std::cerr << "Failed to open job config file: " << job_config_path << '\n';
        return edbarchive::ExitCode::ConfigFileError;
    }

    Json job_config_json;
    try {
        job_config_file >> job_config_json;
    }
    catch (const Json::parse_error& error) {
        std::cerr << "Invalid JSON in job config file: " << error.what() << '\n';
        return edbarchive::ExitCode::InvalidConfigJson;
    }

    if (!job_config_json.contains("job_type")){
        std::cerr << "Missing job type\n";
        return edbarchive::ExitCode::InvalidParameter;
    }

    std::string job_type = job_config_json["job_type"].get<std::string>();

    // check input
    if (!job_config_json.contains("input") ||
        !job_config_json.at("input").is_object()) {

        std::cerr << "Missing or invalid input object\n";
        return edbarchive::ExitCode::InvalidParameter;
    }
    const auto& input_json = job_config_json.at("input");

    fs::path public_dir = workspace_path / edbarchive::PathPublicDirectory / args.job_id;
    fs::path private_dir = workspace_path / edbarchive::PathPrivateDirectory / args.job_id;

    if (job_type == edbarchive::CommandCreateContext) {
        if (!input_json.contains("parameters") ||
            !input_json.at("parameters").is_object()) {

            std::cerr << "Missing or invalid input.parameters object\n";
            return edbarchive::ExitCode::InvalidConfigJson;
        }
        const auto& parameters_json = input_json.at("parameters");

        std::cout << "Executed Command = " << edbarchive::CommandCreateContext << "\n\n";

        // Set CryptoContext
        std::cout << "Configs:\n";
        lbcrypto::CCParams<lbcrypto::CryptoContextBFVRNS> parameters;
        parameters.SetSecurityLevel(lbcrypto::HEStd_128_classic);
        if (parameters_json.contains("t")) {
            parameters.SetPlaintextModulus(parameters_json["t"].get<int>());
            std::cout << "   ├── Plaintext Modulus (t): " << parameters_json["t"].get<int>() << '\n';
        }

        if (parameters_json.contains("N")) {
            parameters.SetRingDim(parameters_json["N"].get<int>());
            std::cout << "   ├── Ring Dimension (N): " << parameters_json["N"].get<int>() << '\n';
        }

        if (parameters_json.contains("depth")) {
            parameters.SetMultiplicativeDepth(parameters_json["depth"].get<int>());
            std::cout << "   ├── Multiplicative Depth: " << parameters_json["depth"].get<int>() << '\n';
        }
        std::cout << "   └── End of configs\n\n";

        lbcrypto::CryptoContext<lbcrypto::DCRTPoly> crypto_context = GenCryptoContext(parameters);
        crypto_context->Enable(lbcrypto::PKE);
        crypto_context->Enable(lbcrypto::KEYSWITCH);
        crypto_context->Enable(lbcrypto::LEVELEDSHE);

        std::cout << "Crypto Context created\n";
        std::cout << "   ├── Poly modulus degree (ring dimension): "
            << crypto_context->GetCryptoParameters()->GetElementParams()->GetRingDimension()
            << '\n';
        std::cout << "   ├── Multiplicative depth: " << parameters.GetMultiplicativeDepth() << '\n';

        size_t numSlots = crypto_context->GetEncodingParams()->GetBatchSize();
        std::cout << "   ├── Number of slots: " << numSlots << '\n';

        // Serialize crypto_context
        if (!lbcrypto::Serial::SerializeToFile((private_dir / edbarchive::PathContextFile).string(), crypto_context, lbcrypto::SerType::BINARY)) {
            std::cerr << "Error serializing context\n";
            return edbarchive::ExitCode::InvalidContextFile;
        }
        std::cout << "   └── The crypto context has been serialized.\n";
    } else if (job_type == edbarchive::CommandCreateKeypair) {
        std::cout << "Executed Command = " << edbarchive::CommandCreateKeypair << "\n\n";

        // create keypair need valid crypto context
        if (!input_json.contains("context_file_ref")) {
            std::cerr << "Missing context_file_ref configuration\n";
            return edbarchive::ExitCode::InvalidConfigJson;
        }

        // check if file exist
        fs::path context_path = workspace_path / input_json["context_file_ref"].get<std::string>();

        // loading crypto context
        std::cout << "Loading context file: " << context_path << '\n';

        if (!fs::exists(context_path) || !fs::is_regular_file(context_path)) {
            std::cerr << "Invalid context file = " << context_path << '\n';
            return edbarchive::ExitCode::InvalidContextFile;
        }
        lbcrypto::CryptoContext<lbcrypto::DCRTPoly> crypto_context;
        if (!lbcrypto::Serial::DeserializeFromFile(context_path, crypto_context, lbcrypto::SerType::BINARY)) {
            std::cerr << "Error loading context file\n";
            return edbarchive::ExitCode::InvalidContextFile;
        }
        std::cout << "   ├── Poly modulus degree (ring dimension) = " << crypto_context->GetCryptoParameters()->GetElementParams()->GetRingDimension() << '\n';
        std::cout << "   ├── Multiplicative depth = " << crypto_context->GetCryptoParameters()->GetElementParams()->GetParams().size() - 1 << '\n';
        std::cout << "   ├── Number of slots = " << crypto_context->GetEncodingParams()->GetBatchSize() << '\n';
        std::cout << "   └── Context file loaded successfully\n\n";

        lbcrypto::KeyPair<lbcrypto::DCRTPoly> key_pair = crypto_context->KeyGen();

        std::cout << "Key pair created\n";

        // Serialize the private key
        if (!lbcrypto::Serial::SerializeToFile(private_dir / edbarchive::PathPrivateKeyFile, key_pair.secretKey, lbcrypto::SerType::BINARY)) {
            std::cerr << "Error serializing private key\n";
            return edbarchive::ExitCode::InvalidKeypairFile;
        }
        std::cout << "   ├── Private key serialized successfully\n";

        // Serialize the public key
        if (!lbcrypto::Serial::SerializeToFile(private_dir / edbarchive::PathPublicKeyFile, key_pair.publicKey, lbcrypto::SerType::BINARY)) {
            std::cerr << "Error serializing public key\n";
            return edbarchive::ExitCode::InvalidKeypairFile;
        }
        std::cout << "   ├── Public key serialized successfully\n";
        std::cout << "   └── All keys serialized successfully\n\n";

    } else if (job_type == edbarchive::CommandBackup) {
        std::cout << "Executed Command = " << edbarchive::CommandBackup << "\n\n";

        if (!input_json.contains("items") || !input_json.at("items").is_array()) {
            std::cerr << "Missing or invalid items\n";
            return edbarchive::ExitCode::ConfigFileError;
        }

        // Load the FHE context. Only one context is currently supported.
        nlohmann::json fhe_items = nlohmann::json::array();

        bool fhe_item_found = false;
        std::string context_file_ref;
        std::string keypair_file_ref;

        std::unordered_map<std::string, std::vector<std::string>> primary_keys;

        for (const auto& c_item : input_json.at("items")) {
            if (c_item.at("pk").get<bool>()) {
                std::string c_table = c_item.at("table_name").get<std::string>();
                std::string c_column = c_item.at("column_name").get<std::string>();
                primary_keys[c_table].push_back(c_column);
            }

            if (c_item.at("encryption").get<std::string>() != "fhe-secure") {
                continue;
            }

            fhe_items.push_back(c_item);

            const auto current_keypair_file_ref = c_item.at("keypair_file_ref").get<std::string>();
            const auto current_context_file_ref = c_item.at("context_file_ref").get<std::string>();

            if (!fhe_item_found) {
                context_file_ref = current_context_file_ref;
                keypair_file_ref = current_keypair_file_ref;
                fhe_item_found = true;
                continue;
            }

            if (current_context_file_ref != context_file_ref) {
                std::cerr << "Multiple FHE contexts are not supported\n";
                return edbarchive::ExitCode::ConfigFileError;
            }

            if (current_keypair_file_ref != keypair_file_ref) {
                std::cerr << "Multiple FHE keypairs are not supported\n";
                return edbarchive::ExitCode::ConfigFileError;
            }
        }

        if (!fhe_item_found) {
            std::cout << "No FHE items to process\n\n";
            return edbarchive::ExitCode::Success;
        }

        // Check the context file and load the FHE context.
        // This logic is duplicated from create-keypair to keep the implementation simple.
        fs::path context_path = workspace_path / context_file_ref;

        std::cout << "Loading context file = " << context_path << '\n';

        if (!fs::exists(context_path) || !fs::is_regular_file(context_path)) {
            std::cerr << "Invalid context file: " << context_path << '\n';
            return edbarchive::ExitCode::InvalidContextFile;
        }

        lbcrypto::CryptoContext<lbcrypto::DCRTPoly> crypto_context;
        if (!lbcrypto::Serial::DeserializeFromFile(context_path, crypto_context, lbcrypto::SerType::BINARY)) {
            std::cerr << "Error loading context file\n";
            return edbarchive::ExitCode::InvalidContextFile;
        }
        std::cout << "   ├── Poly modulus degree (ring dimension) = " << crypto_context->GetCryptoParameters()->GetElementParams()->GetRingDimension() << '\n';
        std::cout << "   ├── Multiplicative depth = " << crypto_context->GetCryptoParameters()->GetElementParams()->GetParams().size() - 1 << '\n';
        std::cout << "   ├── Number of slots = " << crypto_context->GetEncodingParams()->GetBatchSize() << '\n';
        std::cout << "   └── Context file loaded successfully\n\n";

        // ring dimension
        size_t N = static_cast<size_t>(crypto_context->GetCryptoParameters()->GetElementParams()->GetRingDimension());


        // loading key pair files
        std::cout << "Loading key pair file\n";

        // loading public key file
        fs::path public_key_path = workspace_path / keypair_file_ref / edbarchive::PathPublicKeyFile;
        if (!fs::exists(public_key_path) || !fs::is_regular_file(public_key_path)) {
            std::cerr << "Invalid public key file: " << public_key_path << '\n';
            return edbarchive::ExitCode::InvalidKeypairFile;
        }

        lbcrypto::PublicKey<lbcrypto::DCRTPoly> public_key;
        if (!lbcrypto::Serial::DeserializeFromFile(public_key_path, public_key, lbcrypto::SerType::BINARY)) {
            std::cerr << "Could not read public key\n\n";
            return edbarchive::ExitCode::InvalidKeypairFile;
        }
        std::cout << "   ├── Public key loaded successfully\n";

        // loading private key file
        fs::path private_key_path = workspace_path / keypair_file_ref / edbarchive::PathPrivateKeyFile;
        if (!fs::exists(private_key_path) || !fs::is_regular_file(private_key_path)) {
            std::cerr << "Invalid private key file: " << private_key_path << '\n';
            return edbarchive::ExitCode::InvalidKeypairFile;
        }

        lbcrypto::PrivateKey<lbcrypto::DCRTPoly> private_key;
        if (!lbcrypto::Serial::DeserializeFromFile(private_key_path, private_key, lbcrypto::SerType::BINARY)) {
            std::cerr << "Could not read private key\n\n";
            return edbarchive::ExitCode::InvalidKeypairFile;
        }
        std::cout << "   └── Private key loaded successfully\n\n";

        // generate and serialize other keys
        std::cout << "Generating and serializing other keys\n";
        // generate and serialize relinearization key
        crypto_context->EvalMultKeyGen(private_key);
        std::cout << "   ├── Relinearization key generated successfully\n";

        std::ofstream relinearization_stream(public_dir / edbarchive::PathRelinearizationFile, std::ios::out | std::ios::binary);
        if (!crypto_context->SerializeEvalMultKey(relinearization_stream, lbcrypto::SerType::BINARY)) {
            std::cerr << "Error serializing relinearization key\n\n";
            return edbarchive::ExitCode::InvalidOtherKeyFile;
        }
        std::cout << "   ├── Relinearization key serialized successfully\n";
        
        // generate and serialize rotation key (power of two up to N/2 for OpenFHE, for seal up to N/4-1)
        std::vector<int> rotate_key = {};
        for(size_t r = 1; r <= N / 2; r *= 2)
            rotate_key.push_back(static_cast<int>(r));

        crypto_context->EvalRotateKeyGen(private_key, rotate_key);
        std::cout << "   ├── Rotation key generated successfully\n";

        std::ofstream rotation_stream(public_dir / edbarchive::PathRotationFile, std::ios::out | std::ios::binary);
        if (crypto_context->SerializeEvalAutomorphismKey(rotation_stream, lbcrypto::SerType::BINARY) == false) {
            std::cerr << "Error serializing rotation key\n\n";
            return edbarchive::ExitCode::InvalidOtherKeyFile;
        }
        std::cout << "   └── Rotation key serialized successfully\n\n";

        std::string db_name_enc = input_json["database_name"].get<std::string>() + "_edb_" + args.job_id;

        // check DB connection
        std::cout << "Check database connection\n";
        std::unique_ptr<sql::Connection> conn;
        try{
            sql::Driver* driver = sql::mariadb::get_driver_instance();
            sql::SQLString url("jdbc:mariadb://" + 
                                db_config_json["host"].get<std::string>() + ":" + 
                                std::to_string(db_config_json["port"].get<int>()) + "/" + 
                                db_name_enc
                            );
            sql::Properties props({{"user", db_config_json["user"].get<std::string>()}, {"password", db_config_json["pass"].get<std::string>()}});
            conn.reset(driver->connect(url, props));
        }catch (const sql::SQLException& db_ex) {
            std::cerr << "Failed to connect the database: " << db_ex.what() << "\n\n";
            return edbarchive::ExitCode::DBConnectionError;
        }
        std::cout << "   └── Database connection established successfully\n\n";

        std::cout << "Encrypting the database\n";
        fs::path ciphertexts_dir = public_dir / "ciphertexts";

        if (fs::exists(ciphertexts_dir)) {
            std::cout << "   ├── ciphertexts directory already exists, removing existing files\n";
            fs::remove_all(ciphertexts_dir);
        }

        fs::create_directories(ciphertexts_dir);
        std::cout << "   ├── ciphertexts directory created successfully\n";

        // this version only support int data
        std::vector<int64_t> batch;
        const int commit_interval = 1000;
        int pending_updates = 0;
        int ciphertext_num = 1;

        // Optimization to avoid updating too frequently
        conn->setAutoCommit(false);

        for (const auto& c_item : fhe_items) {
            std::string c_table = c_item.at("table_name").get<std::string>();
            std::string c_column = c_item.at("column_name").get<std::string>();

            std::cout << "   ├── Processing " << c_table << "." << c_column << '\n';

            if (primary_keys.find(c_table) == primary_keys.end()) {
                std::cerr << "This table has no primary key configuration, so row ordering cannot be guaranteed\n\n";
                return edbarchive::ExitCode::ConfigFileError;
            }

            std::string c_select = edbarchive::join(primary_keys[c_table]);
            std::string c_update = edbarchive::join(primary_keys[c_table], " = ? AND ");

            std::unique_ptr<sql::PreparedStatement> select_stmt(
                conn->prepareStatement("SELECT "+ c_select + ", " + c_column +" FROM "+ c_table +" ORDER BY " + c_select)
            );
            std::unique_ptr<sql::ResultSet> result_set(select_stmt->executeQuery());

            std::unique_ptr<sql::PreparedStatement> update_stmt(
                conn->prepareStatement(
                    "UPDATE "+ c_table +" SET "+ c_column +" = ? "
                    "WHERE "+ c_update +" = ?"
                )
            );

            while (true) {
                auto has_row = result_set->next();

                if (!has_row) {
                    break;
                }

                batch.push_back(result_set->getInt(c_column));
                update_stmt->setInt(1, ciphertext_num);
                for (size_t i_select = 0; i_select < primary_keys[c_table].size(); ++i_select) {
                    update_stmt->setInt(2+i_select, result_set->getInt(primary_keys[c_table][i_select]));
                }
                update_stmt->executeUpdate();

                pending_updates++;
                if (pending_updates >= commit_interval) {
                    conn->commit();
                    pending_updates = 0;
                }

                if(batch.size() == N){
                    lbcrypto::Plaintext plaintext = crypto_context->MakePackedPlaintext(batch);
                    auto ciphertext = crypto_context->Encrypt(public_key, plaintext);

                    if (!lbcrypto::Serial::SerializeToFile(ciphertexts_dir / (std::to_string(ciphertext_num) + ".bin"), ciphertext, lbcrypto::SerType::BINARY)) {
                        std::cerr << "Error serializing ciphertext " << ciphertext_num << "\n\n";
                        return edbarchive::ExitCode::CiphertextSerializationError;
                    }

                    std::cout << "   │   ├── Ciphertext " << ciphertext_num << " serialized successfully\n";

                    batch.clear();
                    ciphertext_num++;
                }
            }
            std::cout << "   │   └── Finished processing " << c_table << "." << c_column << ", pending updates = " << pending_updates << ", uncommitted batch size = " << batch.size()  << '\n';
        }

        if (batch.size() > 0){
            lbcrypto::Plaintext plaintext = crypto_context->MakePackedPlaintext(batch);
            auto ciphertext = crypto_context->Encrypt(public_key, plaintext);

            if (!lbcrypto::Serial::SerializeToFile(ciphertexts_dir / (std::to_string(ciphertext_num) + ".bin"), ciphertext, lbcrypto::SerType::BINARY)) {
                std::cerr << "Error serializing ciphertext " << ciphertext_num << "\n\n";
                return edbarchive::ExitCode::CiphertextSerializationError;
            }
            std::cout << "   ├── Processing last batch = " << batch.size() << '\n';
            batch.clear();
        }
        if (pending_updates > 0) {
            std::cout << "   ├── Processing last pending update = " << pending_updates << '\n';
            conn->commit();
        }
        conn->setAutoCommit(true);

        std::cout << "   └── All columns encrypted successfully\n\n";
        
    } else {
        std::cerr << "Error: Unsupported command\n";
        return edbarchive::ExitCode::UnsupportedCommand;
    }

    return edbarchive::ExitCode::Success;
}