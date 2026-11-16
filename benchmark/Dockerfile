FROM ubuntu:24.04 AS builder

ARG TARGETARCH
ARG MARIADB_CONNECTOR_CPP_AMD64_SHA256=573c30182d2d76fa34bb0c3dc14a770ea4bdf7cfdaaff4f239a26a75f39c36f9
ARG MARIADB_CONNECTOR_CPP_ARM64_SHA256=24bcbc1321c8e1db031920bbd150a863cdf4554adccfa179c87d0007a734efff

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    ca-certificates \
    cmake \
    curl \
    git \
    jq \
    libmariadb-dev \
    libmariadb-dev-compat \
    libssl-dev \
    mariadb-client \
    nlohmann-json3-dev \
    pigz \
    pkg-config \
    && case "${TARGETARCH}" in \
      amd64) \
        connector_url='https://dlm.mariadb.com/4464930/Connectors/cpp/connector-cpp-1.1.7/mariadb-connector-cpp_1.1.7-1%2Bmaria~noble_amd64.deb'; \
        connector_sha256="${MARIADB_CONNECTOR_CPP_AMD64_SHA256}" \
        ;; \
      arm64) \
        connector_url='https://dlm.mariadb.com/4464899/Connectors/cpp/connector-cpp-1.1.7/mariadb-connector-cpp_1.1.7-1%2Bmaria~noble_arm64.deb'; \
        connector_sha256="${MARIADB_CONNECTOR_CPP_ARM64_SHA256}" \
        ;; \
      *) \
        echo "Unsupported target architecture: ${TARGETARCH}" >&2; \
        exit 1 \
        ;; \
      esac \
    && curl -fsSL "${connector_url}" -o /tmp/mariadb-connector-cpp.deb \
    && echo "${connector_sha256}  /tmp/mariadb-connector-cpp.deb" | sha256sum -c - \
    && apt-get install -y --no-install-recommends /tmp/mariadb-connector-cpp.deb \
    && rm /tmp/mariadb-connector-cpp.deb \
    && rm -rf /var/lib/apt/lists/*

COPY deps/openfhe-development /opt/openfhe

RUN cmake \
      -S /opt/openfhe \
      -B /opt/openfhe/build \
      -DCMAKE_BUILD_TYPE=Release \
      -DGIT_SUBMOD_AUTO=OFF \
      -DBUILD_UNITTESTS=OFF \
      -DBUILD_EXAMPLES=OFF \
      -DBUILD_BENCHMARKS=OFF \
    && cmake --build /opt/openfhe/build -j"$(nproc)" \
    && cmake --install /opt/openfhe/build \
    && ldconfig

WORKDIR /opt/benchmark-src

COPY benchmark/CMakeLists.txt ./CMakeLists.txt
COPY benchmark/common ./common
COPY benchmark/src ./src
COPY benchmark/scripts ./scripts

RUN cmake \
      -S /opt/benchmark-src \
      -B /opt/benchmark-src/build \
      -DCMAKE_BUILD_TYPE=Release \
    && cmake --build /opt/benchmark-src/build -j"$(nproc)" \
    && cmake --install /opt/benchmark-src/build --prefix /opt/benchmark

ENV PATH="/opt/benchmark/bin:${PATH}"

WORKDIR /work

ENTRYPOINT ["benchmark.sh"]

