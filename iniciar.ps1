# Sobe a API Laravel e o painel, cada um em sua janela.
#
# Uso: .\iniciar.ps1
#      .\iniciar.ps1 -Semear    (gera um dia de contagens simuladas antes de subir)
#
# O pipeline de câmeras é separado: veja pipeline-ao-vivo\iniciar.ps1.
param([switch]$Semear)

$aqui = $PSScriptRoot
$php = "C:\Users\muril\tools\php\php.exe"
$servidor = Join-Path $aqui "servidor"
$painel = Join-Path $aqui "dashboard"

if (-not (Test-Path $php)) { Write-Error "PHP nao encontrado em $php. Veja o README."; exit 1 }
if (-not (Test-Path (Join-Path $servidor "vendor"))) { Write-Error "Rode primeiro: $php composer.phar install (na pasta servidor)"; exit 1 }
if (-not (Test-Path (Join-Path $painel "node_modules"))) { Write-Error "Rode primeiro: npm install (na pasta dashboard)"; exit 1 }

# Sobra de uma execucao anterior derruba a nova com erro de porta ocupada.
$antigos = @(Get-CimInstance Win32_Process -Filter "Name='php.exe' or Name='node.exe'" |
    Where-Object { $_.CommandLine -like "*$aqui*" })
if ($antigos) {
    Write-Host "Encerrando $($antigos.Count) processo(s) de uma execucao anterior..."
    $antigos | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Start-Sleep -Seconds 2
}

if ($Semear) {
    & $php (Join-Path $servidor "artisan") mapa:semear-demo --limpar
}

Start-Process -WorkingDirectory $servidor -FilePath $php -ArgumentList "artisan", "serve", "--host=0.0.0.0", "--port=8000"
Start-Process -WorkingDirectory $painel -FilePath "npm.cmd" -ArgumentList "run", "dev"

Write-Host ""
Write-Host "API:     http://localhost:8000/api/v1/resumo"
Write-Host "Painel:  http://localhost:5173"
Write-Host ""
Write-Host "Sem contagem gravada, o painel avisa que nao ha dado."
Write-Host "Para encher com um dia simulado:  .\iniciar.ps1 -Semear"
