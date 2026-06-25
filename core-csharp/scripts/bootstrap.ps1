$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root
try {
    $sdk = (& dotnet --version).Trim()
    if (-not $sdk.StartsWith('10.')) {
        throw "Nexus Scholar requires a .NET 10 SDK. Found: $sdk"
    }

    dotnet restore NexusScholar.Core.slnx
    dotnet build NexusScholar.Core.slnx --configuration Release --no-restore
    dotnet test NexusScholar.Core.slnx --configuration Release --no-build
    dotnet run --project src/NexusScholar.Cli --configuration Release --no-build -- doctor

    Write-Host 'Nexus Scholar C# starter is ready.' -ForegroundColor Green
}
finally {
    Pop-Location
}
