#!/usr/bin/env bash
# shellcheck shell=bash
## @file scripts/verify-mailpit-message.bash
## @brief Verifies the invoice email captured by the staging SMTP sink.
## @details
## Polls a Mailpit v1 API until exactly one message is available, then verifies
## that the message has one example.com recipient, the configured Kimai sender,
## an invoice subject, the maintained invoice-email body, and exactly one
## attachment.  The attachment bytes must match the deterministic PDF payload
## created by InvoiceEmailerControllerTest.
##
## Usage:
## @code
## scripts/verify-mailpit-message.bash http://127.0.0.1:8025
## @endcode
##
## @par STDIN
## Nothing is read from STDIN.
##
## @returns
## Nothing is written to STDOUT.
##
## @retval 0 The expected message and attachment were captured.
## @retval 64 The command-line arguments are invalid.
## @retval 66 A required command is unavailable.
## @retval 70 The expected SMTP message was not captured or did not match.
##
## @note
## curl and jq failures are handled as staging-validation failures rather than
## propagating their raw exit status.
##
## @see tests/Integration/Controller/InvoiceEmailerControllerTest.php
## @see doc/staging-validation.md

set -euo pipefail

readonly EX_USAGE=64
readonly EX_NOINPUT=66
readonly EX_SOFTWARE=70

if (( $# != 1 )); then
  printf 'Usage: %s <mailpit-base-url>\n' "${0}" >&2
  exit "${EX_USAGE}"
fi

for command_name in curl jq cmp mktemp; do
  if ! command -v "${command_name}" >/dev/null 2>&1; then
    printf 'Required command is unavailable: %s\n' "${command_name}" >&2
    exit "${EX_NOINPUT}"
  fi
done

base_url="${1%/}"
readonly base_url

messages_json=''
for _attempt in {1..30}; do
  if messages_json="$(curl --fail --silent --show-error     "${base_url}/api/v1/messages?limit=10" 2>/dev/null)"; then
    if [[ "$(jq -r '.total // 0' <<<"${messages_json}")" == '1' ]]; then
      break
    fi
  fi

  sleep 1
done

if [[ -z "${messages_json}" ]] ||
  [[ "$(jq -r '.total // 0' <<<"${messages_json}")" != '1' ]]; then
  printf 'Expected exactly one SMTP message in Mailpit.\n' >&2
  exit "${EX_SOFTWARE}"
fi

message_json=''
if ! message_json="$(curl --fail --silent --show-error   "${base_url}/api/v1/message/latest")"; then
  printf 'Unable to retrieve the latest Mailpit message.\n' >&2
  exit "${EX_SOFTWARE}"
fi

if ! jq -e '
  (.To | length) == 1
  and (.To[0].Address | type == "string")
  and (.To[0].Address | endswith("@example.com"))
  and .From.Address == "kimai@example.com"
  and (.Subject | startswith("Invoice "))
  and (.Text | contains("is attached to this email."))
  and (.Attachments | length) == 1
  and (.Attachments[0].PartID | type == "string")
  and (.Attachments[0].PartID | length > 0)
' <<<"${message_json}" >/dev/null; then
  printf 'Captured SMTP message did not match the invoice email contract.\n' >&2
  exit "${EX_SOFTWARE}"
fi

part_id="$(jq -r '.Attachments[0].PartID' <<<"${message_json}")"
readonly part_id

attachment_file="$(mktemp)"
expected_file="$(mktemp)"
readonly attachment_file expected_file
trap 'rm -f "${attachment_file}" "${expected_file}"' EXIT

if ! curl --fail --silent --show-error   "${base_url}/api/v1/message/latest/part/${part_id}"   --output "${attachment_file}"; then
  printf 'Unable to download the captured invoice attachment.\n' >&2
  exit "${EX_SOFTWARE}"
fi

printf '%s' '%PDF-1.4 integration test invoice' >"${expected_file}"

if ! cmp -s "${expected_file}" "${attachment_file}"; then
  printf 'Captured invoice attachment bytes did not match the test invoice.\n' >&2
  exit "${EX_SOFTWARE}"
fi

printf 'Published release SMTP validation captured one expected invoice email.\n' >&2
