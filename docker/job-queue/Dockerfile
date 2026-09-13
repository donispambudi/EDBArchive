FROM ubuntu:24.04 AS openfhe-base

ARG MARIADB_REPO_SETUP_SHA256=7325ac7755809ca3312b446bd832542421699298f25b701f9a111bb42df0c7c1
ARG MARIADB_CONNECTOR_CPP_SHA256=573c30182d2d76fa34bb0c3dc14a770ea4bdf7cfdaaff4f239a26a75f39c36f9

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    apt-transport-https \
    ca-certificates \
    curl \
    && curl -fsSL https://r.mariadb.com/downloads/mariadb_repo_setup \
      -o /tmp/mariadb_repo_setup \
    && echo "${MARIADB_REPO_SETUP_SHA256}  /tmp/mariadb_repo_setup" \
      | sha256sum -c - \
    && chmod +x /tmp/mariadb_repo_setup \
    && /tmp/mariadb_repo_setup \
      --mariadb-server-version=mariadb-12.1 \
      --os-type=ubuntu \
      --os-version=noble \
      --skip-maxscale \
    && rm /tmp/mariadb_repo_setup \
    && curl -fsSL \
      'https://dlm.mariadb.com/4464930/Connectors/cpp/connector-cpp-1.1.7/mariadb-connector-cpp_1.1.7-1%2Bmaria~noble_amd64.deb' \
      -o /tmp/mariadb-connector-cpp.deb \
    && echo "${MARIADB_CONNECTOR_CPP_SHA256}  /tmp/mariadb-connector-cpp.deb" \
      | sha256sum -c - \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
    build-essential \
    cmake \
    git \
    python3 \
    python3-dev \
    python3-pip \
    python3-venv \
    libssl-dev \
    libmariadb-dev \
    libmariadb-dev-compat \
    mariadb-client \
    jq \
    pigz \
    pkg-config \
    /tmp/mariadb-connector-cpp.deb \
    && rm /tmp/mariadb-connector-cpp.deb \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY deps/openfhe-development /app/deps/openfhe-development

# Build and install OpenFHE
RUN cmake \
      -S /app/deps/openfhe-development \
      -B /app/deps/openfhe-development/build \
      -DCMAKE_BUILD_TYPE=Release \
      -DGIT_SUBMOD_AUTO=OFF \
      -DBUILD_UNITTESTS=OFF \
      -DBUILD_EXAMPLES=OFF \
      -DBUILD_BENCHMARKS=OFF \
    && cmake --build /app/deps/openfhe-development/build -j"$(nproc)" \
    && cmake --install /app/deps/openfhe-development/build \
    && ldconfig

FROM openfhe-base AS third-party

COPY third-party /app/third-party

RUN cmake \
      -S /app/third-party/openfhe_bfv \
      -B /app/third-party/openfhe_bfv/build \
      -DCMAKE_BUILD_TYPE=Release \
    && cmake --build /app/third-party/openfhe_bfv/build -j"$(nproc)" \
    && cmake --install /app/third-party/openfhe_bfv/build \
      --prefix /app/third-party

WORKDIR /work

ENTRYPOINT ["/app/third-party/third_party.sh"]

FROM openfhe-base AS job-queue

COPY backend /app/backend
COPY job-queue /app/job-queue

# Build and install the OpenFHE BFV backend
RUN cmake \
      -S /app/backend/openfhe_bfv \
      -B /app/backend/openfhe_bfv/build \
      -DCMAKE_BUILD_TYPE=Release \
    && cmake --build /app/backend/openfhe_bfv/build -j"$(nproc)" \
    && cmake --install /app/backend/openfhe_bfv/build --prefix /app

# Python environment
RUN python3 -m venv /opt/venv

ENV PATH="/opt/venv/bin:$PATH"

RUN pip install --no-cache-dir -r /app/job-queue/requirements.txt

WORKDIR /app/job-queue

CMD ["python", "edbarchive_service.py"]
