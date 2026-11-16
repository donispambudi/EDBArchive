#pragma once

#include <chrono>
#include <cctype>
#include <cstdlib>
#include <filesystem>
#include <fstream>
#include <map>
#include <memory>
#include <stdexcept>
#include <string>
#include <vector>

#include <mariadb/conncpp.hpp>
#include <nlohmann/json.hpp>

namespace benchmark {

namespace fs = std::filesystem;
using Json = nlohmann::json;

enum class Method {
    Baseline,
    Carpov,
    Erkin,
    Edbarchive,
};

inline Method parseMethod(const std::string& value) {
    if (value == "baseline") {
        return Method::Baseline;
    }
    if (value == "carpov") {
        return Method::Carpov;
    }
    if (value == "erkin") {
        return Method::Erkin;
    }
    if (value == "edbarchive") {
        return Method::Edbarchive;
    }
    throw std::runtime_error("Unknown benchmark method: " + value);
}

inline std::string methodName(Method method) {
    switch (method) {
        case Method::Baseline:
            return "baseline";
        case Method::Carpov:
            return "carpov";
        case Method::Erkin:
            return "erkin";
        case Method::Edbarchive:
            return "edbarchive";
    }
    throw std::runtime_error("Invalid benchmark method.");
}

class Arguments {
public:
    Arguments(int argc, char* argv[]) {
        for (int index = 1; index < argc; ++index) {
            const std::string name = argv[index];
            if (name.rfind("--", 0) != 0) {
                throw std::runtime_error("Unexpected positional argument: " + name);
            }
            if (index + 1 >= argc) {
                throw std::runtime_error("Missing value for argument: " + name);
            }
            values_[name.substr(2)] = argv[++index];
        }
    }

    std::string require(const std::string& name) const {
        const auto iterator = values_.find(name);
        if (iterator == values_.end() || iterator->second.empty()) {
            throw std::runtime_error("Missing required argument: --" + name);
        }
        return iterator->second;
    }

    std::string optional(const std::string& name, const std::string& fallback = "") const {
        const auto iterator = values_.find(name);
        return iterator == values_.end() ? fallback : iterator->second;
    }

private:
    std::map<std::string, std::string> values_;
};

inline std::string environment(const char* name, const std::string& fallback = "") {
    const char* value = std::getenv(name);
    return value == nullptr ? fallback : std::string(value);
}

struct DatabaseConfig {
    std::string host;
    int port;
    std::string user;
    std::string password;
};

inline DatabaseConfig databaseConfigFromEnvironment() {
    const std::string port = environment("BENCHMARK_DB_PORT", "3306");
    return {
        environment("BENCHMARK_DB_HOST", "127.0.0.1"),
        std::stoi(port),
        environment("BENCHMARK_DB_USER", "edbarchive"),
        environment("BENCHMARK_DB_PASSWORD", "edbarchive"),
    };
}

inline bool isSafeIdentifier(const std::string& value) {
    if (value.empty()) {
        return false;
    }
    for (const unsigned char character : value) {
        if (!std::isalnum(character) && character != '_') {
            return false;
        }
    }
    return true;
}

inline std::string quoteIdentifier(const std::string& value) {
    if (!isSafeIdentifier(value)) {
        throw std::runtime_error("Unsafe SQL identifier: " + value);
    }
    return "`" + value + "`";
}

inline std::string joinIdentifiers(
    const std::vector<std::string>& values,
    const std::string& delimiter = ", "
) {
    std::string result;
    for (std::size_t index = 0; index < values.size(); ++index) {
        result += quoteIdentifier(values[index]);
        if (index + 1 < values.size()) {
            result += delimiter;
        }
    }
    return result;
}

inline std::unique_ptr<sql::Connection> connectDatabase(
    const DatabaseConfig& config,
    const std::string& database
) {
    if (!isSafeIdentifier(database)) {
        throw std::runtime_error("Unsafe database name: " + database);
    }
    sql::Driver* driver = sql::mariadb::get_driver_instance();
    const sql::SQLString url(
        "jdbc:mariadb://" + config.host + ":" + std::to_string(config.port) + "/" + database
    );
    const sql::Properties properties({
        {"user", config.user},
        {"password", config.password},
    });
    return std::unique_ptr<sql::Connection>(driver->connect(url, properties));
}

struct TableConfig {
    std::string name;
    std::vector<std::string> primaryKeys;
    std::vector<std::string> encryptedColumns;
};

struct RunConfig {
    int erkinBitWidth;
    std::vector<TableConfig> tables;
    Json raw;
};

inline RunConfig loadRunConfig(const fs::path& path) {
    std::ifstream stream(path);
    if (!stream.is_open()) {
        throw std::runtime_error("Cannot open benchmark configuration: " + path.string());
    }

    Json document;
    stream >> document;

    if (!document.contains("tables") || !document["tables"].is_array() || document["tables"].empty()) {
        throw std::runtime_error("Benchmark configuration must contain a non-empty tables array.");
    }

    int bitWidth = 14;
    if (document.contains("fhe") && document["fhe"].is_object()) {
        bitWidth = document["fhe"].value("erkin_bit_width", 14);
    }
    if (bitWidth < 1 || bitWidth > 30) {
        throw std::runtime_error("erkin_bit_width must be between 1 and 30.");
    }

    std::vector<TableConfig> tables;
    for (const auto& item : document["tables"]) {
        if (!item.is_object()) {
            throw std::runtime_error("Each table configuration must be an object.");
        }
        TableConfig table{
            item.at("name").get<std::string>(),
            item.at("primary_keys").get<std::vector<std::string>>(),
            item.at("encrypted_columns").get<std::vector<std::string>>(),
        };
        if (!isSafeIdentifier(table.name) || table.primaryKeys.empty() || table.encryptedColumns.empty()) {
            throw std::runtime_error("Invalid or incomplete table configuration.");
        }
        for (const auto& key : table.primaryKeys) {
            if (!isSafeIdentifier(key)) {
                throw std::runtime_error("Invalid primary-key identifier: " + key);
            }
        }
        for (const auto& column : table.encryptedColumns) {
            if (!isSafeIdentifier(column)) {
                throw std::runtime_error("Invalid encrypted-column identifier: " + column);
            }
        }
        tables.push_back(std::move(table));
    }

    return {bitWidth, std::move(tables), std::move(document)};
}

inline void writeJson(const fs::path& path, const Json& document) {
    std::ofstream stream(path);
    if (!stream.is_open()) {
        throw std::runtime_error("Cannot write JSON file: " + path.string());
    }
    stream << document.dump(2) << '\n';
}

inline double elapsedMilliseconds(const std::chrono::steady_clock::time_point& startedAt) {
    return std::chrono::duration<double, std::milli>(
        std::chrono::steady_clock::now() - startedAt
    ).count();
}

}  // namespace benchmark

