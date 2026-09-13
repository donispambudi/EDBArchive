#pragma once

#include <string>
#include <vector>

namespace edbarchive {

    inline const std::string PathConfigFile = "config.json";
    inline const std::string PathContextFile = "context.bin";
    inline const std::string PathRelinearizationFile = "relinearization.bin";
    inline const std::string PathRotationFile = "rotation.bin";
    inline const std::string PathCiphertextDir = "ciphertexts";

    enum ExitCode {
        Success = 0,
        GeneralError = 1,
        InvalidParameter = 2,
        ConfigFileError = 3,
        InvalidConfigJson = 4,
        InvalidContextFile = 5,
        InvalidRotationKeyFile = 6,
        DBConnectionError = 7,
        InvalidCiphertextFile = 8
    };

    struct BackendArgs {
        std::string bundle_path;
        std::string restore_db_name;
        std::string db_config_path;
    };

    std::string join(const std::vector<std::string>& v, const std::string& delimiter=",") {
        std::string result;
        for (size_t i = 0; i < v.size(); ++i) {
            result += v[i];
            if (i + 1 < v.size())
                result += delimiter;
        }
        return result;
    }
}