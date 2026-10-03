#!/usr/bin/env bash
# shellcheck shell=bash
## @file scripts/build-release.bash
## @brief Builds and verifies the Kimai plugin release ZIP.
## @details
## This script creates the public distribution artifact defined by ADR-004.
## Source files are selected from a committed Git tree, with export exclusions
## controlled by the repository's .gitattributes file.  The selected tree is
## staged beneath the required InvoiceEmailerBundle directory and serialized as
## an uncompressed ZIP with fixed timestamps, stable ordering, and normalized
## Unix permissions.  These choices make the archive byte-for-byte reproducible
## for the same exported tree, independent of the source commit timestamp.
##
## The requested release version must match composer.json.  After creating the
## archive, the script verifies ZIP integrity, the required top-level layout,
## the presence of runtime/legal files, the absence of development-only paths,
## and the generated SHA-256 sidecar checksum.
##
## Usage:
## @code
## scripts/build-release.bash 0.2.0 dist
## scripts/build-release.bash 0.2.0 dist HEAD
## @endcode
##
## The script writes the absolute archive path to STDOUT on success.  Diagnostics
## and the checksum path are written to STDERR.
##
## @see doc/adr/ADR-004-deterministic-release-artifacts.md
## @see doc/release.md

set -euo pipefail

readonly BUNDLE_NAME='InvoiceEmailerBundle'
readonly EX_USAGE=64
readonly EX_DATAERR=65
readonly EX_NOINPUT=66
readonly EX_SOFTWARE=70

if (( $# < 2 || $# > 3 )); then
  printf 'Usage: %s <version> <output-directory> [git-ref]\n' "${0}" >&2
  exit "${EX_USAGE}"
fi

version="${1}"
output_directory="${2}"
git_ref="${3:-HEAD}"

if [[ ! "${version}" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  printf 'Version must use major.minor.patch form: %s\n' "${version}" >&2
  exit "${EX_USAGE}"
fi

for command_name in git php python3 tar unzip sha256sum; do
  if ! command -v "${command_name}" >/dev/null 2>&1; then
    printf 'Required command is unavailable: %s\n' "${command_name}" >&2
    exit "${EX_NOINPUT}"
  fi
done

repo_root=''
if ! repo_root="$(git rev-parse --show-toplevel)"; then
  printf 'Unable to determine the Git repository root.\n' >&2
  exit "${EX_NOINPUT}"
fi
readonly repo_root

cd "${repo_root}"

commit=''
if ! commit="$(git rev-parse --verify "${git_ref}^{commit}")"; then
  printf 'Git reference does not resolve to a commit: %s\n' "${git_ref}" >&2
  exit "${EX_NOINPUT}"
fi
readonly commit

composer_version=''
if ! composer_version="$(
  php -r '
    $data = json_decode(
        file_get_contents("composer.json"),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    echo $data["version"] ?? "";
  '
)"; then
  printf 'Unable to read the version from composer.json.\n' >&2
  exit "${EX_DATAERR}"
fi

if [[ "${composer_version}" != "${version}" ]]; then
  printf 'composer.json version %s does not match requested version %s.\n'     "${composer_version}" "${version}" >&2
  exit "${EX_DATAERR}"
fi

mkdir -p "${output_directory}"
output_directory="$(
  cd "${output_directory}"
  pwd -P
)"
readonly output_directory

archive_name="${BUNDLE_NAME}-${version}.zip"
checksum_name="${archive_name}.sha256"
archive_path="${output_directory}/${archive_name}"
checksum_path="${output_directory}/${checksum_name}"
readonly archive_name checksum_name archive_path checksum_path

rm -f "${archive_path}" "${checksum_path}"

staging_root="$(mktemp -d)"
readonly staging_root
trap 'rm -rf "${staging_root}"' EXIT

git archive   --format=tar   --prefix="${BUNDLE_NAME}/"   "${commit}"   >"${staging_root}/source.tar"

tar -xf "${staging_root}/source.tar" -C "${staging_root}"
rm -f "${staging_root}/source.tar"

python3 - "${staging_root}" "${archive_path}" <<'PY'
import os
import stat
import sys
from pathlib import Path
from zipfile import ZIP_STORED, ZipFile, ZipInfo

staging_root = Path(sys.argv[1])
archive_path = Path(sys.argv[2])
bundle_root = staging_root / "InvoiceEmailerBundle"

if not bundle_root.is_dir():
    raise SystemExit("staged archive root is missing InvoiceEmailerBundle/")

paths = sorted(
    bundle_root.rglob("*"),
    key=lambda path: path.relative_to(staging_root).as_posix(),
)

with ZipFile(archive_path, "w", compression=ZIP_STORED, allowZip64=True) as zip_file:
    for path in paths:
        if path.is_symlink():
            raise SystemExit(f"release archive may not contain symlinks: {path}")

        relative = path.relative_to(staging_root).as_posix()
        is_directory = path.is_dir()
        if is_directory:
            relative += "/"

        info = ZipInfo(relative, date_time=(1980, 1, 1, 0, 0, 0))
        info.create_system = 3
        info.compress_type = ZIP_STORED

        if is_directory:
            mode = stat.S_IFDIR | 0o755
            info.external_attr = (mode << 16) | 0x10
            zip_file.writestr(info, b"")
            continue

        source_mode = path.stat().st_mode
        permissions = 0o755 if source_mode & stat.S_IXUSR else 0o644
        mode = stat.S_IFREG | permissions
        info.external_attr = mode << 16
        zip_file.writestr(info, path.read_bytes())
PY

if ! unzip -tq "${archive_path}" >/dev/null; then
  printf 'Generated ZIP failed its integrity check: %s\n' "${archive_path}" >&2
  exit "${EX_SOFTWARE}"
fi

mapfile -t archive_entries < <(unzip -Z1 "${archive_path}")
if (( ${#archive_entries[@]} == 0 )); then
  printf 'Generated ZIP contains no entries.\n' >&2
  exit "${EX_SOFTWARE}"
fi

for archive_entry in "${archive_entries[@]}"; do
  if [[ "${archive_entry}" != "${BUNDLE_NAME}/"* ]]; then
    printf 'Archive entry is outside the required bundle root: %s\n'       "${archive_entry}" >&2
    exit "${EX_SOFTWARE}"
  fi
done

required_entries=(
  "${BUNDLE_NAME}/InvoiceEmailerBundle.php"
  "${BUNDLE_NAME}/Resources/config/services.yaml"
  "${BUNDLE_NAME}/Resources/config/routes.yaml"
  "${BUNDLE_NAME}/LICENSE"
  "${BUNDLE_NAME}/README.md"
  "${BUNDLE_NAME}/CHANGELOG.md"
  "${BUNDLE_NAME}/UPSTREAM.md"
  "${BUNDLE_NAME}/composer.json"
)

for required_entry in "${required_entries[@]}"; do
  if ! printf '%s\n' "${archive_entries[@]}" |
    grep -Fxq "${required_entry}"; then
    printf 'Required release entry is missing: %s\n'       "${required_entry}" >&2
    exit "${EX_SOFTWARE}"
  fi
done

prohibited_pattern="^${BUNDLE_NAME}/(\\.github|doc|scripts|tests)(/|$)"
prohibited_pattern+="|^${BUNDLE_NAME}/(AGENTS\\.md|CODEOWNERS|CONTRIBUTING\\.md)$"
prohibited_pattern+="|^${BUNDLE_NAME}/(phpstan\\.neon|phpunit\\.xml\\.dist)$"

if printf '%s\n' "${archive_entries[@]}" |
  grep -Eq "${prohibited_pattern}"; then
  printf 'Release ZIP contains development-only files.\n' >&2
  exit "${EX_SOFTWARE}"
fi

(
  cd "${output_directory}"
  sha256sum "${archive_name}" >"${checksum_name}"
  sha256sum -c "${checksum_name}" >/dev/null
)

printf '%s\n' "${archive_path}"
printf 'Checksum: %s\n' "${checksum_path}" >&2
