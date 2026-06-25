$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root
try {
    dotnet build NexusScholar.Core.slnx --configuration Release
    dotnet test NexusScholar.Core.slnx --configuration Release --no-build
    dotnet format NexusScholar.Core.slnx --verify-no-changes --no-restore
}
finally {
    Pop-Location
}
