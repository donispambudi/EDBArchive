#include "../common/edbarchive.hpp"
#include "../common/json.hpp"

#include <cstddef>
#include <cstdint>
#include <cstdlib>
#include <filesystem>
#include <fstream>
#include <iostream>
#include <sstream>
#include <string>
#include <vector>

#include <mariadb/conncpp.hpp>

#include "openfhe.h"
#include "cryptocontext-ser.h"
#include "key/key-ser.h"
#include "ciphertext-ser.h"
#include "scheme/bfvrns/bfvrns-ser.h"

namespace fs = std::filesystem;
using Json = nlohmann::json;

namespace {

bool isNonEmptyStringArray(const Json& value) {
    if (!value.is_array() || value.empty()) {
        return false;
    }

    for (const auto& item : value) {
        if (!item.is_string() || item.get_ref<const std::string&>().empty()) {
            return false;
        }
    }

    return true;
}

std::string quoteIdentifier(const std::string& identifier) {
    std::string quoted = "`";
    for (const char character : identifier) {
        quoted += character;
        if (character == '`') {
            quoted += '`';
        }
    }
    quoted += '`';
    return quoted;
}

}  // namespace

int main(int argc, char* argv[]) {
    const char* wrapper_flag = std::getenv("RUN_FROM_WRAPPER");
    if(wrapper_flag == nullptr || std::string(wrapper_flag) != "1") {
        std::cerr << "Command cannot be executed directly, run it from wrapper\n";
        return edbarchive::ExitCode::GeneralError;
    }

    if (argc != 4) {
        std::cerr << "Invalid backend arguments\n";
        return edbarchive::ExitCode::InvalidParameter;
    }

    edbarchive::BackendArgs args;
    args.bundle_path = argv[1];
    args.restore_db_name = argv[2];
    args.db_config_path = argv[3];

    std::cout << "Received Parameters:\n";
    std::cout << "   ├── Bundle Path = " << args.bundle_path << '\n';
    std::cout << "   ├── Restore DB Name = " << args.restore_db_name << "\n";
    std::cout << "   └── DB Config File Path = " << args.db_config_path << "\n\n";

    // read config json
    std::cout << "Loading config file:\n";

    fs::path bundle_path = fs::path(args.bundle_path);
    if (!fs::is_directory(bundle_path)) {
        std::cerr << "Bundle directory does not exist: " << bundle_path << '\n';
        return edbarchive::ExitCode::InvalidParameter;
    }

    fs::path config_path = bundle_path / edbarchive::PathConfigFile;

    std::ifstream config_file(config_path);
    if (!config_file.is_open()) {
        std::cerr << "Failed to open config file: " << config_path << '\n';
        return edbarchive::ExitCode::ConfigFileError;
    }

    Json config_json;
    try {
        config_file >> config_json;
    }
    catch (const Json::parse_error& error) {
        std::cerr << "Invalid JSON in config file: " << error.what() << '\n';
        return edbarchive::ExitCode::InvalidConfigJson;
    }

    if (!config_json.contains("tables") || !config_json["tables"].is_array()) {
        std::cerr << "Invalid config: tables is missing or is not an array\n";
        return edbarchive::ExitCode::InvalidConfigJson;
    }

    for (const auto& table : config_json["tables"]) {
        if (!table.is_object() ||
            !table.contains("name") ||
            !table["name"].is_string() ||
            table["name"].get_ref<const std::string&>().empty() ||
            !table.contains("primary_keys") ||
            !isNonEmptyStringArray(table["primary_keys"]) ||
            !table.contains("encrypted_columns") ||
            !isNonEmptyStringArray(table["encrypted_columns"])) {
            std::cerr << "Invalid table definition in config\n";
            return edbarchive::ExitCode::InvalidConfigJson;
        }
    }
    std::cout << "   └── Configuration file loaded successfully\n\n";

    fs::path context_path = bundle_path / edbarchive::PathContextFile;
    std::cout << "Loading context:" << '\n';
    lbcrypto::CryptoContext<lbcrypto::DCRTPoly> crypto_context;
    if (!lbcrypto::Serial::DeserializeFromFile(bundle_path / edbarchive::PathContextFile, crypto_context, lbcrypto::SerType::BINARY)) {
        std::cerr << "Error load crypto context from "<< context_path << "\n\n";
        return edbarchive::ExitCode::InvalidContextFile;
    }
    std::cout << "   ├── Poly modulus degree (ring dimension) = " << crypto_context->GetCryptoParameters()->GetElementParams()->GetRingDimension() << '\n';
    std::cout << "   ├── Multiplicative depth = " << crypto_context->GetCryptoParameters()->GetElementParams()->GetParams().size() - 1 << '\n';
    std::cout << "   ├── Number of slots = " << crypto_context->GetEncodingParams()->GetBatchSize() << '\n';
    std::cout << "   └── Context file loaded successfully\n\n";

    // ring dimension
    int N = static_cast<int>(crypto_context->GetCryptoParameters()->GetElementParams()->GetRingDimension());

    // create rotation key
    std::vector<int> rotation_keys = {};
    for(int r = 1; r <= N / 2; r *= 2)
        rotation_keys.push_back(r);

    std::cout << "Loading rotation key:" << '\n';
    fs::path rotation_path = bundle_path / edbarchive::PathRotationFile;
    std::ifstream rotation_key(rotation_path, std::ios::in | std::ios::binary);
    if (!rotation_key.is_open()) {
        std::cerr << "Error load rotation key from " << rotation_path << "\n\n";
        return edbarchive::ExitCode::InvalidRotationKeyFile;
    }
    std::cout << "   └── Rotation key loaded successfully\n\n";

    if (!crypto_context->DeserializeEvalAutomorphismKey(rotation_key, lbcrypto::SerType::BINARY)) {
        std::cerr << "Error load rotation keys from " << rotation_path << '\n';  
        return edbarchive::ExitCode::InvalidRotationKeyFile;
    }

    // load db json config
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

    if (!db_config_json.contains("host") ||
        !db_config_json["host"].is_string() ||
        db_config_json["host"].get_ref<const std::string&>().empty() ||
        !db_config_json.contains("port") ||
        !db_config_json["port"].is_number_integer() ||
        db_config_json["port"].get<std::int64_t>() < 1 ||
        db_config_json["port"].get<std::int64_t>() > 65535 ||
        !db_config_json.contains("user") ||
        !db_config_json["user"].is_string() ||
        db_config_json["user"].get_ref<const std::string&>().empty() ||
        !db_config_json.contains("pass") ||
        !db_config_json["pass"].is_string()) {
        std::cerr << "Invalid database configuration\n";
        return edbarchive::ExitCode::InvalidConfigJson;
    }

    std::cout << "Check database connection\n";
    std::unique_ptr<sql::Connection> conn;
    try{
        sql::Driver* driver = sql::mariadb::get_driver_instance();
        sql::SQLString url("jdbc:mariadb://" + 
                            db_config_json["host"].get<std::string>() + ":" + 
                            std::to_string(db_config_json["port"].get<int>()) + "/" + 
                            args.restore_db_name
                        );
        sql::Properties props({{"user", db_config_json["user"].get<std::string>()}, {"password", db_config_json["pass"].get<std::string>()}});
        conn.reset(driver->connect(url, props));
    }catch (const sql::SQLException& db_ex) {
        std::cerr << "Failed to connect the database: " << db_ex.what() << "\n\n";
        return edbarchive::ExitCode::DBConnectionError;
    }
    std::cout << "   └── Database connection established successfully\n\n";

    std::cout << "Restoring data:" << '\n';

    std::unique_ptr<sql::Statement> schema_stmt(conn->createStatement());
    std::unique_ptr<sql::Statement> select_stmt(conn->createStatement());

    // Since ciphertext BLOBs make each database commit relatively large,
    // the batch size is limited to 20 records.
    const int commit_interval = 20;
    int pending_updates = 0;
    int ciphertext_num = 0;
    int slot_num = 0;

    lbcrypto::Ciphertext<lbcrypto::DCRTPoly> current_ciphertext;

    // Optimization to avoid updating too frequently
    conn->setAutoCommit(false);

    // process table
    for (const auto& c_item : config_json["tables"]) {
        std::string c_table = c_item["name"].get<std::string>();

        std::vector<std::string> c_pk_vec = c_item["primary_keys"].get<std::vector<std::string>>();

        std::vector<std::string> quoted_pk_vec;
        quoted_pk_vec.reserve(c_pk_vec.size());
        for (const auto& primary_key : c_pk_vec) {
            quoted_pk_vec.push_back(quoteIdentifier(primary_key));
        }

        const std::string quoted_table = quoteIdentifier(c_table);
        const std::string c_select = edbarchive::join(quoted_pk_vec);
        const std::string c_update = edbarchive::join(quoted_pk_vec, " = ? AND ");

        for (const auto& c_encrypted : c_item["encrypted_columns"]) {
            std::string c_column = c_encrypted.get<std::string>();
            const std::string quoted_column = quoteIdentifier(c_column);
            const std::string quoted_encrypted_column = quoteIdentifier(c_column + "_enc");

            std::cout << "   ├── Restoring " << c_table << "." << c_column << '\n';

            schema_stmt->execute("ALTER TABLE " + quoted_table + " ADD COLUMN " + quoted_encrypted_column + " MEDIUMBLOB DEFAULT NULL AFTER " + quoted_column);
            std::cout << "   │   ├── Add column " << c_column << "_enc\n";

            // query column data
            std::unique_ptr<sql::ResultSet> result_set(
                select_stmt->executeQuery(
                    "SELECT " + c_select + ", " + quoted_column +
                    " FROM " + quoted_table +
                    " ORDER BY " + c_select)
            );

            std::unique_ptr<sql::PreparedStatement> update_stmt(
                conn->prepareStatement(
                    "UPDATE " + quoted_table + " SET " + quoted_encrypted_column + " = ? "
                    "WHERE " + c_update + " = ?"
                )
            );

            while (true) {
                auto has_row = result_set->next();
                if (!has_row) {
                    break;
                }

                int c_ciphertext_ref = result_set->getInt(c_column);

                if (c_ciphertext_ref != ciphertext_num) {
                    fs::path c_ciphertext_path = bundle_path / edbarchive::PathCiphertextDir / (std::to_string(c_ciphertext_ref) + ".bin");
                    if (!lbcrypto::Serial::DeserializeFromFile(c_ciphertext_path, current_ciphertext, lbcrypto::SerType::BINARY)) {
                        std::cerr << "Failed to load ciphertext from " << c_ciphertext_path << '\n';
                        return edbarchive::ExitCode::InvalidCiphertextFile;
                    }

                    slot_num = 1;
                    ciphertext_num = c_ciphertext_ref;
                }

                if (slot_num > N){
                    std::cerr << "Slot number (" << slot_num << ") > maximum slot available (" << N << ")\n";
                    return edbarchive::ExitCode::InvalidCiphertextFile;
                }

                lbcrypto::Ciphertext<lbcrypto::DCRTPoly> rotated_ciphertext = current_ciphertext->Clone();

                int align_slot = slot_num - 1;
                int rotate_i = rotation_keys.size() - 1;
                while(align_slot > 0){
                    if(align_slot >= rotation_keys[rotate_i]){
                        //rotate
                        rotated_ciphertext = crypto_context->EvalRotate(rotated_ciphertext, rotation_keys[rotate_i]);
                        align_slot -= rotation_keys[rotate_i];
                    }else{
                        rotate_i--;
                    }
                }

                // serialize into binary
                try {
                    std::ostringstream ciphertext_oss(std::ios::out | std::ios::binary);
                    lbcrypto::Serial::Serialize(rotated_ciphertext, ciphertext_oss, lbcrypto::SerType::BINARY);
                    std::string blob_data = ciphertext_oss.str();
                    std::istringstream blob_stream(blob_data, std::ios::in | std::ios::binary);

                    update_stmt->setBlob(1, &blob_stream);
                    for (size_t i_order = 0; i_order < c_pk_vec.size(); ++i_order) {
                        const auto& c_pk = c_pk_vec[i_order];
                        update_stmt->setInt(2 + i_order, result_set->getInt(c_pk));
                    }
                    update_stmt->executeUpdate();

                    pending_updates++;
                    if (pending_updates >= commit_interval) {
                        conn->commit();
                        pending_updates = 0;
                    }
                } catch (const std::exception& e) {
                    std::cerr << "Failed to serialize ciphertext: " << e.what() << std::endl;
                    return edbarchive::ExitCode::InvalidCiphertextFile;
                }

                slot_num++;
            }
        
            schema_stmt->execute("ALTER TABLE " + quoted_table + " DROP COLUMN " + quoted_column);
            std::cout << "   │   ├── Drop column " << c_column << "_enc\n";

            schema_stmt->execute("ALTER TABLE " + quoted_table + " RENAME COLUMN " + quoted_encrypted_column + " TO " + quoted_column);
            std::cout << "   │   ├── Rename column from " << c_column << "_enc to " << c_column << '\n';
            std::cout << "   │   └── Column " << c_table << "." << c_column << " restored successfully\n";
        }
    }

    if (pending_updates > 0) {
        conn->commit();
    }
    conn->setAutoCommit(true);

    std::cout << "   └── All data restored successfully\n\n";

    return edbarchive::ExitCode::Success;
}
