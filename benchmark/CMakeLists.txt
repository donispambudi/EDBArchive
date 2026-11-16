cmake_minimum_required(VERSION 3.16.3)

project(edbarchive_benchmark LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_CXX_EXTENSIONS OFF)

find_package(OpenFHE CONFIG REQUIRED)
find_package(nlohmann_json CONFIG REQUIRED)

find_path(MARIADBCPP_INCLUDE_DIR NAMES mariadb/conncpp.hpp)
find_library(MARIADBCPP_LIBRARY NAMES mariadbcpp)

if(NOT MARIADBCPP_INCLUDE_DIR OR NOT MARIADBCPP_LIBRARY)
    message(FATAL_ERROR "MariaDB Connector/C++ headers or library were not found.")
endif()

separate_arguments(
    OPENFHE_COMPILE_OPTIONS
    NATIVE_COMMAND
    "${OpenFHE_CXX_FLAGS}"
)

function(add_benchmark_executable target source)
    add_executable(${target} ${source})
    target_include_directories(
        ${target}
        PRIVATE
            ${CMAKE_CURRENT_SOURCE_DIR}/common
            ${OpenFHE_INCLUDE}
            ${OpenFHE_INCLUDE}/third-party/include
            ${OpenFHE_INCLUDE}/core
            ${OpenFHE_INCLUDE}/pke
            ${OpenFHE_INCLUDE}/binfhe
            ${OPENMP_INCLUDES}
            ${MARIADBCPP_INCLUDE_DIR}
    )
    target_compile_options(${target} PRIVATE ${OPENFHE_COMPILE_OPTIONS})
    target_link_libraries(
        ${target}
        PRIVATE
            ${OpenFHE_LIBRARIES}
            ${MARIADBCPP_LIBRARY}
            nlohmann_json::nlohmann_json
    )
endfunction()

add_benchmark_executable(benchmark-backup src/backup.cpp)
add_benchmark_executable(benchmark-restore src/restore.cpp)

install(
    TARGETS benchmark-backup benchmark-restore
    RUNTIME DESTINATION bin
)

install(
    PROGRAMS
        scripts/benchmark.sh
        scripts/run-backup.sh
        scripts/run-restore.sh
        scripts/run-scenarios.sh
    DESTINATION bin
)

