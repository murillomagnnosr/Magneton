# Sobe o pipeline inteiro: MediaMTX, worker e dashboard, cada um em sua janela.
#
# Uso: .\iniciar.ps1              (acrescente -Mostrar para abrir as janelas de conferência)
#      .\iniciar.ps1 -ComApi      (também envia as janelas fechadas para a API Laravel)
#
# A API Laravel sobe à parte, na pasta servidor:
#   php artisan serve --host=0.0.0.0 --port=8000
param([switch]$Mostrar, [switch]$ComApi)

$aqui = $PSScriptRoot
$python = Join-Path $aqui ".venv\Scripts\python.exe"
if (-not (Test-Path $python)) { Write-Error "Ambiente .venv não encontrado. Veja o README."; exit 1 }
if (-not (Test-Path (Join-Path $aqui "bin\mediamtx.exe"))) { Write-Error "bin\mediamtx.exe não encontrado. Veja o README."; exit 1 }

# Sobra de uma execução anterior derruba a nova (porta 1935 ocupada, dois workers contando
# a mesma coisa). Fecha tudo antes de subir.
$antigos = @(Get-CimInstance Win32_Process -Filter "Name='mediamtx.exe' or Name='python.exe'" |
    Where-Object { $_.CommandLine -like "*$aqui*" -and $_.CommandLine -notlike "*iniciar.ps1*" })
if ($antigos) {
    Write-Host "Encerrando $($antigos.Count) processo(s) de uma execucao anterior..."
    $antigos | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
    Start-Sleep -Seconds 2
}

Start-Process -WorkingDirectory $aqui -FilePath (Join-Path $aqui "bin\mediamtx.exe") -ArgumentList "mediamtx.yml"
Start-Sleep -Seconds 1
$argsWorker = @("worker.py"); if ($Mostrar) { $argsWorker += "--mostrar" }
Start-Process -WorkingDirectory $aqui -FilePath $python -ArgumentList $argsWorker
Start-Process -WorkingDirectory $aqui -FilePath $python -ArgumentList "-m", "streamlit", "run", "dashboard.py", "--server.headless", "true"
if ($ComApi) {
    # A API fica fechada por chave quando esta publicada; o enviador le /api/v1/config.
    $envArquivo = Join-Path (Split-Path $aqui -Parent) "servidor\.env"
    if (Test-Path $envArquivo) {
        $linha = Select-String -Path $envArquivo -Pattern '^MAPA_CHAVE_LEITURA=(.+)$' | Select-Object -First 1
        if ($linha) { $env:MAPA_CHAVE_LEITURA = $linha.Matches[0].Groups[1].Value.Trim() }
    }
    Start-Process -WorkingDirectory $aqui -FilePath $python -ArgumentList "enviar_para_api.py"
}

# Endereço para o Larix: o hotspot do Windows (192.168.137.1) tem prioridade;
# sem hotspot ligado, usa o IP do Wi-Fi da casa.
$ips = Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike "127.*" -and $_.IPAddress -notlike "169.*" }
$ip = ($ips | Where-Object { $_.IPAddress -eq "192.168.137.1" } | Select-Object -First 1).IPAddress
if (-not $ip) { $ip = ($ips | Where-Object { $_.PrefixOrigin -eq "Dhcp" } | Select-Object -First 1).IPAddress }
if (-not $ip) { $ip = "192.168.137.1" }

Write-Host ""
Write-Host "Dashboard:        http://localhost:8501"
if ($ComApi) { Write-Host "API Laravel:      http://localhost:8000/api/v1/resumo" }
Write-Host "No Larix, use:    rtmp://${ip}:1935/live/cam1   (live/cam2 para o segundo celular)"
if ($ip -eq "192.168.137.1") {
    Write-Host "Ligue o ponto de acesso movel do Windows e conecte os celulares nele."
} else {
    Write-Host "Celulares e PC precisam estar na mesma rede Wi-Fi."
}
