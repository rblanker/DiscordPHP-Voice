#!/usr/bin/env bash

set -euo pipefail

LIBDAVE_VERSION="${LIBDAVE_VERSION:-v1.1.1/cpp}"
LIBDAVE_ASSET="${LIBDAVE_ASSET:-libdave-Linux-X64-boringssl.zip}"
LIBDAVE_BASE_URL="${LIBDAVE_BASE_URL:-https://github.com/discord/libdave/releases/download}"
LIBDAVE_DEST_DIR="${LIBDAVE_DEST_DIR:-.cache/libdave}"
LIBDAVE_ZIP_PATH="${LIBDAVE_ZIP_PATH:-${LIBDAVE_DEST_DIR}/libdave.zip}"
LIBDAVE_VERSION_FILE="${LIBDAVE_DEST_DIR}/.version"

if [[ "$(uname -s)" != "Linux" ]]; then
    echo "setup-libdave.sh only supports Linux runners and local Linux hosts" >&2
    exit 1
fi

case "$(uname -m)" in
    x86_64|amd64)
        ;;
    *)
        echo "setup-libdave.sh only supports Linux x64 for published prebuilt assets" >&2
        exit 1
        ;;
esac

mkdir -p "${LIBDAVE_DEST_DIR}"

if [[ -f "${LIBDAVE_VERSION_FILE}" ]] && [[ "$(<"${LIBDAVE_VERSION_FILE}")" == "${LIBDAVE_VERSION}|${LIBDAVE_ASSET}" ]] && [[ -f "${LIBDAVE_DEST_DIR}/lib/libdave.so" ]]; then
    echo "libdave already installed at ${LIBDAVE_DEST_DIR}"
    exit 0
fi

rm -rf "${LIBDAVE_DEST_DIR}/include" "${LIBDAVE_DEST_DIR}/lib" "${LIBDAVE_DEST_DIR}/licenses"

curl -L --fail --silent --show-error \
    -o "${LIBDAVE_ZIP_PATH}" \
    "${LIBDAVE_BASE_URL}/${LIBDAVE_VERSION//\//%2F}/${LIBDAVE_ASSET}"

unzip -oq "${LIBDAVE_ZIP_PATH}" -d "${LIBDAVE_DEST_DIR}"
printf '%s' "${LIBDAVE_VERSION}|${LIBDAVE_ASSET}" > "${LIBDAVE_VERSION_FILE}"

echo "Installed libdave to ${LIBDAVE_DEST_DIR}"
echo "DISCORDPHP_DAVE_LIBRARY=${LIBDAVE_DEST_DIR}/lib/libdave.so"
