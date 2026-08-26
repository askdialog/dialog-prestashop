#!/bin/bash
# Deploy askdialog.zip to S3 production bucket
# Uses AWS profile: dialog-production

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MODULE_NAME="askdialog"
ZIP_FILE="$HOME/${MODULE_NAME}.zip"
S3_BUCKET="dialog-organization-produ-storageecommercebucket7d-2c5ebvwexta8"
S3_KEY="prestashop-modules/askdialog.zip"
AWS_PROFILE="dialog-prod"

# Console colors
if command -v tput >/dev/null 2>&1; then
  RED="$(tput setaf 1)"
  GREEN="$(tput setaf 2)"
  YELLOW="$(tput setaf 3)"
  BOLD="$(tput bold)"
  RESET="$(tput sgr0)"
else
  RED=$'\033[31m'
  GREEN=$'\033[32m'
  YELLOW=$'\033[33m'
  BOLD=$'\033[1m'
  RESET=$'\033[0m'
fi

# Check AWS CLI
if ! command -v aws >/dev/null 2>&1; then
  echo -e "${RED}${BOLD}✖ ERROR: AWS CLI is not installed.${RESET}" >&2
  echo -e "${RED}Please install AWS CLI and run this script again.${RESET}" >&2
  exit 1
fi

# Check AWS profile exists
if ! aws configure list --profile "$AWS_PROFILE" >/dev/null 2>&1; then
  echo -e "${RED}${BOLD}✖ ERROR: AWS profile '$AWS_PROFILE' not found.${RESET}" >&2
  echo -e "${RED}Please configure the profile: aws configure --profile $AWS_PROFILE${RESET}" >&2
  exit 1
fi

echo -e "${YELLOW}🔨 Building module zip...${RESET}"
"$SCRIPT_DIR/module-build-zip.sh"

if [ ! -f "$ZIP_FILE" ]; then
  echo -e "${RED}${BOLD}✖ ERROR: Build failed - $ZIP_FILE not found.${RESET}" >&2
  exit 1
fi

echo -e "${YELLOW}☁️  Uploading to S3...${RESET}"
aws s3 cp "$ZIP_FILE" "s3://${S3_BUCKET}/${S3_KEY}" --profile "$AWS_PROFILE"

echo -e "${GREEN}${BOLD}✅ Successfully deployed to s3://${S3_BUCKET}/${S3_KEY}${RESET}"
