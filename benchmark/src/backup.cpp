#include <chrono>
#include <cstdint>
#include <filesystem>
#include <fstream>
#include <iostream>
#include <stdexcept>
#include <string>
#include <vector>

#include "benchmark.hpp"
#include "fhe.hpp"

namespace fs = std::filesystem;

int main(int argc, char* argv[]) {
    try {
        const auto started_at = std::chrono::steady_clock::now();
        const benchmark::Arguments arguments(argc, argv);
        const benchmark::Method method = benchmark::parseMethod(arguments.require("method"));
        const fs::path config_path = arguments.require("config");
        const fs::path context_path = arguments.require("context");
        const fs::path public_key_path = arguments.require("public-key");
        const fs::path private_key_path = arguments.require("private-key");
        const fs::path output_directory = arguments.require("output-dir");
        const fs::path metrics_path = arguments.optional(
            "metrics",
            (output_directory / "backup-metrics.json").string()
        );
        const std::string database = arguments.require("database");

        const benchmark::RunConfig config = benchmark::loadRunConfig(config_path);
        lbcrypto::CryptoContext<lbcrypto::DCRTPoly> crypto_context;
        if (!fs::is_regular_file(context_path) ||
            !lbcrypto::Serial::DeserializeFromFile(
                context_path,
                crypto_context,
                lbcrypto::SerType::BINARY
            )) {
            throw std::runtime_error("Cannot deserialize FHE context: " + context_path.string());
        }

        lbcrypto::PublicKey<lbcrypto::DCRTPoly> public_key;
        if (!fs::is_regular_file(public_key_path) ||
            !lbcrypto::Serial::DeserializeFromFile(
                public_key_path,
                public_key,
                lbcrypto::SerType::BINARY
            )) {
            throw std::runtime_error("Cannot deserialize public key: " + public_key_path.string());
        }

        lbcrypto::PrivateKey<lbcrypto::DCRTPoly> private_key;
        if (!fs::is_regular_file(private_key_path) ||
            !lbcrypto::Serial::DeserializeFromFile(
                private_key_path,
                private_key,
                lbcrypto::SerType::BINARY
            )) {
            throw std::runtime_error(
                "Cannot deserialize private key: " + private_key_path.string()
            );
        }
        const std::size_t slot_count = crypto_context->GetEncodingParams()->GetBatchSize();

        if (slot_count == 0) {
            throw std::runtime_error("The existing context reports zero packing slots.");
        }
        if (fs::exists(output_directory) &&
            (!fs::is_directory(output_directory) || !fs::is_empty(output_directory))) {
            throw std::runtime_error(
                "The output directory must be empty: " + output_directory.string()
            );
        }

        fs::path ciphertexts_directory = output_directory / "ciphertexts";
        fs::create_directories(ciphertexts_directory);
        fs::copy_file(
            context_path,
            output_directory / "context.bin",
            fs::copy_options::overwrite_existing
        );

        crypto_context->EvalMultKeyGen(private_key);
        std::ofstream relinearization_stream(
            output_directory / "relinearization.bin",
            std::ios::out | std::ios::binary
        );
        if (!relinearization_stream.is_open() ||
            !crypto_context->SerializeEvalMultKey(
                relinearization_stream,
                lbcrypto::SerType::BINARY
            )) {
            throw std::runtime_error("Cannot serialize the relinearization key.");
        }

        if (method == benchmark::Method::Edbarchive) {
            std::vector<int> rotation_indices;
            for (std::size_t index = 1; index <= slot_count / 2; index *= 2) {
                rotation_indices.push_back(static_cast<int>(index));
            }
            crypto_context->EvalRotateKeyGen(private_key, rotation_indices);
            std::ofstream rotation_stream(
                output_directory / "rotation.bin",
                std::ios::out | std::ios::binary
            );
            if (!rotation_stream.is_open() ||
                !crypto_context->SerializeEvalAutomorphismKey(
                    rotation_stream,
                    lbcrypto::SerType::BINARY
                )) {
                throw std::runtime_error("Cannot serialize the EDBArchive rotation key.");
            }
        }

        benchmark::Json bundle_config = config.raw;
        bundle_config["benchmark"] = {
            {"method", benchmark::methodName(method)},
            {"slot_count", slot_count},
        };
        benchmark::writeJson(output_directory / "config.json", bundle_config);

        auto connection = benchmark::connectDatabase(
            benchmark::databaseConfigFromEnvironment(),
            database
        );

        std::vector<std::int64_t> batch;
        batch.reserve(slot_count);
        const int commit_interval = 1000;
        int pending_updates = 0;
        int ciphertext_number = 1;
        std::uint64_t processed_rows = 0;
        std::uint64_t ciphertext_count = 0;
        bool has_pending_erkin_value = false;
        std::uint64_t pending_erkin_value = 0;
        const std::uint64_t erkin_mask =
            (std::uint64_t{1} << config.erkinBitWidth) - 1;

        connection->setAutoCommit(false);

        try {
            for (const auto& table : config.tables) {
                const std::string quoted_table = benchmark::quoteIdentifier(table.name);
                const std::string selected_keys =
                    benchmark::joinIdentifiers(table.primaryKeys);
                const std::string updated_keys =
                    benchmark::joinIdentifiers(table.primaryKeys, " = ? AND ");

                for (const auto& column : table.encryptedColumns) {
                    const std::string quoted_column = benchmark::quoteIdentifier(column);
                    std::cout << "Backing up " << table.name << "." << column << '\n';

                    std::unique_ptr<sql::PreparedStatement> select_statement(
                        connection->prepareStatement(
                            "SELECT " + selected_keys + ", " + quoted_column +
                            " FROM " + quoted_table + " ORDER BY " + selected_keys
                        )
                    );
                    std::unique_ptr<sql::ResultSet> result_set(
                        select_statement->executeQuery()
                    );
                    std::unique_ptr<sql::PreparedStatement> update_statement(
                        connection->prepareStatement(
                            "UPDATE " + quoted_table + " SET " + quoted_column + " = ? "
                            "WHERE " + updated_keys + " = ?"
                        )
                    );

                    while (result_set->next()) {
                        const std::int64_t value = result_set->getInt(column);

                        if (method == benchmark::Method::Erkin) {
                            if (value < 0 || static_cast<std::uint64_t>(value) > erkin_mask) {
                                throw std::runtime_error(
                                    table.name + "." + column +
                                    " contains a value outside the Erkin " +
                                    std::to_string(config.erkinBitWidth) + "-bit range."
                                );
                            }

                            if (!has_pending_erkin_value) {
                                pending_erkin_value = static_cast<std::uint64_t>(value);
                                has_pending_erkin_value = true;
                            } else {
                                batch.push_back(static_cast<std::int64_t>(
                                    (pending_erkin_value << config.erkinBitWidth) |
                                    static_cast<std::uint64_t>(value)
                                ));
                                has_pending_erkin_value = false;
                            }
                        } else {
                            batch.push_back(value);
                        }

                        update_statement->setInt(1, ciphertext_number);
                        for (std::size_t key_index = 0;
                             key_index < table.primaryKeys.size();
                             ++key_index) {
                            update_statement->setInt(
                                2 + static_cast<int>(key_index),
                                result_set->getInt(table.primaryKeys[key_index])
                            );
                        }
                        update_statement->executeUpdate();

                        ++pending_updates;
                        ++processed_rows;
                        if (pending_updates >= commit_interval) {
                            connection->commit();
                            pending_updates = 0;
                        }

                        const bool batch_is_full =
                            method == benchmark::Method::Baseline || batch.size() == slot_count;
                        if (batch_is_full) {
                            lbcrypto::Plaintext plaintext;
                            if (method == benchmark::Method::Carpov) {
                                plaintext = crypto_context->MakeCoefPackedPlaintext(batch);
                            } else {
                                plaintext = crypto_context->MakePackedPlaintext(batch);
                            }
                            lbcrypto::Ciphertext<lbcrypto::DCRTPoly> ciphertext =
                                crypto_context->Encrypt(public_key, plaintext);

                            if (!lbcrypto::Serial::SerializeToFile(
                                    ciphertexts_directory /
                                        (std::to_string(ciphertext_number) + ".bin"),
                                    ciphertext,
                                    lbcrypto::SerType::BINARY
                                )) {
                                throw std::runtime_error(
                                    "Cannot serialize ciphertext " +
                                    std::to_string(ciphertext_number) + "."
                                );
                            }

                            batch.clear();
                            ++ciphertext_number;
                            ++ciphertext_count;
                        }
                    }
                }
            }

            if (has_pending_erkin_value) {
                batch.push_back(static_cast<std::int64_t>(
                    pending_erkin_value << config.erkinBitWidth
                ));
            }

            if (!batch.empty()) {
                lbcrypto::Plaintext plaintext;
                if (method == benchmark::Method::Carpov) {
                    plaintext = crypto_context->MakeCoefPackedPlaintext(batch);
                } else {
                    plaintext = crypto_context->MakePackedPlaintext(batch);
                }
                lbcrypto::Ciphertext<lbcrypto::DCRTPoly> ciphertext =
                    crypto_context->Encrypt(public_key, plaintext);

                if (!lbcrypto::Serial::SerializeToFile(
                        ciphertexts_directory /
                            (std::to_string(ciphertext_number) + ".bin"),
                        ciphertext,
                        lbcrypto::SerType::BINARY
                    )) {
                    throw std::runtime_error(
                        "Cannot serialize ciphertext " +
                        std::to_string(ciphertext_number) + "."
                    );
                }
                batch.clear();
                ++ciphertext_count;
            }

            if (pending_updates > 0) {
                connection->commit();
            }
            connection->setAutoCommit(true);
        } catch (...) {
            connection->rollback();
            throw;
        }

        const benchmark::Json metrics = {
            {"phase", "backup"},
            {"method", benchmark::methodName(method)},
            {"rows", processed_rows},
            {"ciphertexts", ciphertext_count},
            {"slot_count", slot_count},
            {"elapsed_ms", benchmark::elapsedMilliseconds(started_at)},
        };
        benchmark::writeJson(metrics_path, metrics);
        std::cout << metrics.dump(2) << '\n';
        return 0;
    } catch (const std::exception& error) {
        std::cerr << "Backup failed: " << error.what() << '\n';
        return 1;
    }
}
