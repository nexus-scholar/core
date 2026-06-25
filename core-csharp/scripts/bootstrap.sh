#!/usr/bin/env bash
set -euo pipefail
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$root"

sdk="$(dotnet --version)"
if [[ "$sdk" != 10.* ]]; then
  echo "Nexus Scholar requires a .NET 10 SDK. Found: $sdk" >&2
  exit 1
fi

dotnet restore NexusScholar.Core.slnx
dotnet build NexusScholar.Core.slnx --configuration Release --no-restore
dotnet test NexusScholar.Core.slnx --configuration Release --no-build
dotnet run --project src/NexusScholar.Cli --configuration Release --no-build -- doctor

echo "Nexus Scholar C# starter is ready."
