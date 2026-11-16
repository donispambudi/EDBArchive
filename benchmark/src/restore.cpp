#include <chrono>
#include <cstdint>
#include <filesystem>
#include <fstream>
#include <iostream>
#include <sstream>
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
        const fs::path bundle_directory = arguments.require("bundle-dir");
        const bool cleanup_ciphertexts =
            arguments.optional("cleanup-ciphertexts", "false") == "true";
        const fs::path metrics_path = arguments.optional(
            "metrics",
            (bundle_directory / "restore-metrics.json").string()
        );
        const std::string database = arguments.require("database");

        const benchmark::RunConfig config =
            benchmark::loadRunConfig(bundle_directory / "config.json");
        if (!config.raw.contains("benchmark") ||
            config.raw["benchmark"].value("method", "") != benchmark::methodName(method)) {
            throw std::runtime_error(
                "The requested method does not match the method recorded in the bundle."
            );
        }

        const fs::path context_path = bundle_directory / "context.bin";
        lbcrypto::CryptoContext<lbcrypto::DCRTPoly> crypto_context;
        if (!fs::is_regular_file(context_path) ||
            !lbcrypto::Serial::DeserializeFromFile(
                context_path,
                crypto_context,
                lbcrypto::SerType::BINARY
            )) {
            throw std::runtime_error("Cannot deserialize FHE context: " + context_path.string());
        }
        const std::size_t slot_count = crypto_context->GetEncodingParams()->GetBatchSize();
        if (config.raw["benchmark"].value("slot_count", std::size_t{0}) != slot_count) {
            throw std::runtime_error("The bundle slot count does not match its FHE context.");
        }

        lbcrypto::PrivateKey<lbcrypto::DCRTPoly> private_key;
        lbcrypto::PublicKey<lbcrypto::DCRTPoly> public_key;
        if (method == benchmark::Method::Carpov || method == benchmark::Method::Erkin) {
            const fs::path key_directory = arguments.require("key-dir");
            const fs::path private_key_path = key_directory / "private.bin";
            const fs::path public_key_path = key_directory / "public.bin";
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
            if (!fs::is_regular_file(public_key_path) ||
                !lbcrypto::Serial::DeserializeFromFile(
                    public_key_path,
                    public_key,
                    lbcrypto::SerType::BINARY
                )) {
                throw std::runtime_error(
                    "Cannot deserialize public key: " + public_key_path.string()
                );
            }
        } else if (method == benchmark::Method::Edbarchive) {
            std::ifstream rotation_stream(
                bundle_directory / "rotation.bin",
                std::ios::in | std::ios::binary
            );
            if (!rotation_stream.is_open() ||
                !crypto_context->DeserializeEvalAutomorphismKey(
                    rotation_stream,
                    lbcrypto::SerType::BINARY
                )) {
                throw std::runtime_error("Cannot deserialize the EDBArchive rotation key.");
            }
        }

        std::vector<int> rotation_indices;
        for (std::size_t index = 1; index <= slot_count / 2; index *= 2) {
            rotation_indices.push_back(static_cast<int>(index));
        }

        auto connection = benchmark::connectDatabase(
            benchmark::databaseConfigFromEnvironment(),
            database
        );
        std::unique_ptr<sql::Statement> schema_statement(connection->createStatement());
        std::unique_ptr<sql::Statement> select_statement(connection->createStatement());

        const int commit_interval = 20;
        int pending_updates = 0;
        int ciphertext_number = 0;
        int active_ciphertext_reference = 0;
        std::size_t slot_number = 0;
        std::uint64_t restored_rows = 0;
        double cleanup_elapsed_ms = 0.0;
        const std::uint64_t erkin_mask =
            (std::uint64_t{1} << config.erkinBitWidth) - 1;
        lbcrypto::Ciphertext<lbcrypto::DCRTPoly> current_ciphertext;
        std::vector<std::int64_t> current_values;
        std::vector<fs::path> ciphertexts_pending_cleanup;

        const auto ciphertextPath = [&](int reference) {
            return bundle_directory / "ciphertexts" /
                (std::to_string(reference) + ".bin");
        };
        const auto removeCommittedCiphertexts = [&]() {
            if (!cleanup_ciphertexts || ciphertexts_pending_cleanup.empty()) {
                return;
            }
            const auto cleanup_started_at = std::chrono::steady_clock::now();
            for (const auto& path : ciphertexts_pending_cleanup) {
                std::error_code error;
                const bool removed = fs::remove(path, error);
                if (error || !removed) {
                    throw std::runtime_error(
                        "Cannot remove consumed ciphertext: " + path.string()
                    );
                }
            }
            ciphertexts_pending_cleanup.clear();
            cleanup_elapsed_ms += benchmark::elapsedMilliseconds(cleanup_started_at);
        };
        const auto commitAndCleanup = [&]() {
            connection->commit();
            removeCommittedCiphertexts();
        };

        connection->setAutoCommit(false);

        for (const auto& table : config.tables) {
            const std::string quoted_table = benchmark::quoteIdentifier(table.name);
            const std::string selected_keys = benchmark::joinIdentifiers(table.primaryKeys);
            const std::string updated_keys =
                benchmark::joinIdentifiers(table.primaryKeys, " = ? AND ");

            for (const auto& column : table.encryptedColumns) {
                const std::string quoted_column = benchmark::quoteIdentifier(column);
                const std::string encrypted_column = column + "_enc";
                const std::string quoted_encrypted_column =
                    benchmark::quoteIdentifier(encrypted_column);
                std::cout << "Restoring " << table.name << "." << column << '\n';

                schema_statement->execute(
                    "ALTER TABLE " + quoted_table + " ADD COLUMN " +
                    quoted_encrypted_column + " MEDIUMBLOB DEFAULT NULL AFTER " +
                    quoted_column
                );

                std::unique_ptr<sql::ResultSet> result_set(
                    select_statement->executeQuery(
                        "SELECT " + selected_keys + ", " + quoted_column +
                        " FROM " + quoted_table + " ORDER BY " + selected_keys
                    )
                );
                std::unique_ptr<sql::PreparedStatement> update_statement(
                    connection->prepareStatement(
                        "UPDATE " + quoted_table + " SET " + quoted_encrypted_column +
                        " = ? WHERE " + updated_keys + " = ?"
                    )
                );

                while (result_set->next()) {
                    const int ciphertext_reference = result_set->getInt(column);
                    const fs::path ciphertext_path = ciphertextPath(ciphertext_reference);
                    std::string blob_data;

                    if (ciphertext_reference != active_ciphertext_reference) {
                        if (cleanup_ciphertexts && active_ciphertext_reference != 0 &&
                            ciphertext_reference < active_ciphertext_reference) {
                            throw std::runtime_error(
                                "Ciphertext references are not monotonic; incremental "
                                "cleanup is unsafe."
                            );
                        }
                        if (active_ciphertext_reference != 0 && cleanup_ciphertexts) {
                            ciphertexts_pending_cleanup.push_back(
                                ciphertextPath(active_ciphertext_reference)
                            );
                        }
                        active_ciphertext_reference = ciphertext_reference;
                    }

                    if (method == benchmark::Method::Baseline) {
                        std::ifstream ciphertext_stream(
                            ciphertext_path,
                            std::ios::in | std::ios::binary
                        );
                        if (!ciphertext_stream.is_open()) {
                            throw std::runtime_error(
                                "Cannot open ciphertext file: " + ciphertext_path.string()
                            );
                        }
                        std::ostringstream blob_buffer(std::ios::out | std::ios::binary);
                        blob_buffer << ciphertext_stream.rdbuf();
                        blob_data = blob_buffer.str();
                    } else {
                        if (ciphertext_reference != ciphertext_number) {
                            if (!fs::is_regular_file(ciphertext_path) ||
                                !lbcrypto::Serial::DeserializeFromFile(
                                    ciphertext_path,
                                    current_ciphertext,
                                    lbcrypto::SerType::BINARY
                                )) {
                                throw std::runtime_error(
                                    "Cannot deserialize ciphertext: " +
                                    ciphertext_path.string()
                                );
                            }
                            ciphertext_number = ciphertext_reference;
                            slot_number = 1;

                            if (method == benchmark::Method::Carpov ||
                                method == benchmark::Method::Erkin) {
                                lbcrypto::Plaintext current_plaintext;
                                crypto_context->Decrypt(
                                    private_key,
                                    current_ciphertext,
                                    &current_plaintext
                                );
                                current_plaintext->SetLength(slot_count);
                                current_values = method == benchmark::Method::Carpov
                                    ? current_plaintext->GetCoefPackedValue()
                                    : current_plaintext->GetPackedValue();
                            }
                        }

                        if (method == benchmark::Method::Edbarchive) {
                            if (slot_number > slot_count) {
                                throw std::runtime_error(
                                    "EDBArchive slot index exceeds the available slots."
                                );
                            }

                            lbcrypto::Ciphertext<lbcrypto::DCRTPoly> rotated_ciphertext =
                                current_ciphertext->Clone();
                            int remaining_slots = static_cast<int>(slot_number - 1);
                            int rotation_index =
                                static_cast<int>(rotation_indices.size()) - 1;

                            while (remaining_slots > 0) {
                                if (rotation_index < 0) {
                                    throw std::runtime_error(
                                        "Cannot align the EDBArchive ciphertext slot."
                                    );
                                }
                                if (remaining_slots >= rotation_indices[rotation_index]) {
                                    rotated_ciphertext = crypto_context->EvalRotate(
                                        rotated_ciphertext,
                                        rotation_indices[rotation_index]
                                    );
                                    remaining_slots -= rotation_indices[rotation_index];
                                } else {
                                    --rotation_index;
                                }
                            }
                            std::ostringstream ciphertext_stream(
                                std::ios::out | std::ios::binary
                            );
                            lbcrypto::Serial::Serialize(
                                rotated_ciphertext,
                                ciphertext_stream,
                                lbcrypto::SerType::BINARY
                            );
                            blob_data = ciphertext_stream.str();
                        } else {
                            std::int64_t value = 0;
                            if (method == benchmark::Method::Carpov) {
                                if (slot_number == 0 || slot_number > current_values.size()) {
                                    throw std::runtime_error(
                                        "Carpov slot index exceeds the decoded plaintext."
                                    );
                                }
                                value = current_values[slot_number - 1];
                            } else {
                                const std::size_t packed_index = (slot_number - 1) / 2;
                                const std::size_t packed_position = (slot_number - 1) % 2;
                                if (slot_number == 0 || packed_index >= current_values.size()) {
                                    throw std::runtime_error(
                                        "Erkin slot index exceeds the decoded plaintext."
                                    );
                                }
                                const std::uint64_t packed_value =
                                    static_cast<std::uint64_t>(current_values[packed_index]);
                                value = packed_position == 0
                                    ? static_cast<std::int64_t>(
                                        packed_value >> config.erkinBitWidth
                                    )
                                    : static_cast<std::int64_t>(
                                        packed_value & erkin_mask
                                    );
                            }

                            lbcrypto::Plaintext plaintext =
                                crypto_context->MakePackedPlaintext({value});
                            lbcrypto::Ciphertext<lbcrypto::DCRTPoly> ciphertext =
                                crypto_context->Encrypt(public_key, plaintext);
                            std::ostringstream ciphertext_stream(
                                std::ios::out | std::ios::binary
                            );
                            lbcrypto::Serial::Serialize(
                                ciphertext,
                                ciphertext_stream,
                                lbcrypto::SerType::BINARY
                            );
                            blob_data = ciphertext_stream.str();
                        }

                        ++slot_number;
                    }

                    std::istringstream blob_stream(
                        blob_data,
                        std::ios::in | std::ios::binary
                    );
                    update_statement->setBlob(1, &blob_stream);
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
                    ++restored_rows;
                    if (pending_updates >= commit_interval) {
                        commitAndCleanup();
                        pending_updates = 0;
                    }
                }

                schema_statement->execute(
                    "ALTER TABLE " + quoted_table + " DROP COLUMN " + quoted_column
                );
                schema_statement->execute(
                    "ALTER TABLE " + quoted_table + " RENAME COLUMN " +
                    quoted_encrypted_column + " TO " + quoted_column
                );
            }
        }

        if (active_ciphertext_reference != 0 && cleanup_ciphertexts) {
            ciphertexts_pending_cleanup.push_back(
                ciphertextPath(active_ciphertext_reference)
            );
        }
        if (pending_updates > 0) {
            commitAndCleanup();
        } else {
            removeCommittedCiphertexts();
        }
        connection->setAutoCommit(true);

        const double backend_elapsed_ms =
            benchmark::elapsedMilliseconds(started_at) - cleanup_elapsed_ms;

        const benchmark::Json metrics = {
            {"phase", "restore"},
            {"method", benchmark::methodName(method)},
            {"rows", restored_rows},
            {"slot_count", slot_count},
            {"elapsed_ms", backend_elapsed_ms},
            {"ciphertext_cleanup_ms", cleanup_elapsed_ms},
        };
        benchmark::writeJson(metrics_path, metrics);
        std::cout << metrics.dump(2) << '\n';
        return 0;
    } catch (const std::exception& error) {
        std::cerr << "Restore failed: " << error.what() << '\n';
        return 1;
    }
}
