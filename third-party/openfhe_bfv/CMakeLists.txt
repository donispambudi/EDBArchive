cmake_minimum_required(VERSION 3.16.3)

project(openfhe_bfv LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_CXX_EXTENSIONS OFF)
set(CMAKE_EXPORT_COMPILE_COMMANDS ON)

# OpenFHE is expected to have been installed with `cmake --install`.
# A non-standard installation can still be selected with
# `-DOpenFHE_DIR=/path/to/OpenFHE` when configuring this project.
find_package(OpenFHE CONFIG REQUIRED)

find_path(
    MARIADBCPP_INCLUDE_DIR
    NAMES mariadb/conncpp.hpp
)

find_library(
    MARIADBCPP_LIBRARY
    NAMES mariadbcpp
)

if(NOT MARIADBCPP_INCLUDE_DIR OR NOT MARIADBCPP_LIBRARY)
    message(
        FATAL_ERROR
        "MariaDB Connector/C++ headers or library were not found."
    )
endif()

add_executable(openfhe_bfv openfhe_bfv.cpp)

target_include_directories(
    openfhe_bfv
    PRIVATE
        ${OpenFHE_INCLUDE}
        ${OpenFHE_INCLUDE}/third-party/include
        ${OpenFHE_INCLUDE}/core
        ${OpenFHE_INCLUDE}/pke
        ${OpenFHE_INCLUDE}/binfhe
        ${OPENMP_INCLUDES}
        ${MARIADBCPP_INCLUDE_DIR}
)

separate_arguments(
    OPENFHE_COMPILE_OPTIONS
    NATIVE_COMMAND
    "${OpenFHE_CXX_FLAGS}"
)

target_compile_options(openfhe_bfv PRIVATE ${OPENFHE_COMPILE_OPTIONS})

target_link_libraries(
    openfhe_bfv
    PRIVATE
        ${OpenFHE_LIBRARIES}
        ${MARIADBCPP_LIBRARY}
)

install(
    TARGETS openfhe_bfv
    RUNTIME DESTINATION bin
)

message(STATUS "OpenFHE version: ${BASE_OPENFHE_VERSION}")
message(STATUS "MariaDB Connector/C++ include directory: ${MARIADBCPP_INCLUDE_DIR}")
message(STATUS "MariaDB Connector/C++ library: ${MARIADBCPP_LIBRARY}")
