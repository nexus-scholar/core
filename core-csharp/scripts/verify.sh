#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

dotnet build NexusScholar.Core.slnx --configuration Release
dotnet test NexusScholar.Core.slnx --configuration Release --no-build
dotnet format NexusScholar.Core.slnx --verify-no-changes --no-restore
