#pragma once

#include <string>
#include <vector>

namespace edbarchive {

    inline const std::string PathJobsDirectory = "fhe-jobs";
    inline const std::string PathConfigFile = "config.json";
    inline const std::string PathResultFile = "result.json";
    inline const std::string PathPublicDirectory = "public";
    inline const std::string PathPrivateDirectory = "private";
    inline const std::string PathContextFile = "context.bin";
    inline const std::string PathRelinearizationFile = "relinearization.bin";
    inline const std::string PathRotationFile = "rotation.bin";
    inline const std::string PathPrivateKeyFile = "private.bin";
    inline const std::string PathPublicKeyFile = "public.bin";

    inline const std::string CommandCreateContext = "create-context";
    inline const std::string CommandCreateKeypair = "create-keypair";
    inline const std::string CommandBackup = "backup";

    enum ExitCode {
        Success = 0,
        GeneralError = 1,
        InvalidParameter = 2,
        UnsupportedCommand = 3,
        ConfigFileError = 4,
        InvalidConfigJson = 5,
        InvalidContextFile = 6,
        InvalidKeypairFile = 7,
        DBConnectionError = 8,
        InvalidOtherKeyFile = 9,
        CiphertextSerializationError = 10
    };

    struct BackendArgs {
        std::string workspace_path;
        std::string job_id;
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