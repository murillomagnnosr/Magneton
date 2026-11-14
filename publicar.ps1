# Abre o tunel que deixa a API deste computador acessivel pela internet.
#
# O painel fica na Vercel, mas quem conta pessoas e este PC: ele e que tem as cameras na
# rede e o modelo rodando. O tunel e a ponte entre os dois. Enquanto esta janela estiver
# aberta o endereco existe; ao fechar, some.
#
# Uso: .\publicar.ps1
#      .\publicar.ps1 -Dominio seu-dominio.ngrok-free.app   (endereco fixo da conta)
#
# Antes da primeira vez, uma vez so, com o token da sua conta em ngrok.com:
#      C:\Users\muril\tools\ngrok\ngrok.exe config add-authtoken SEU_TOKEN
param(
    [string]$Dominio = "",
    [int]$Porta = 8000
)

$aqui = $PSScriptRoot
$ngrok = "C:\Users\muril\tools\ngrok\ngrok.exe"
$php = "C:\Users\muril\tools\php\php.exe"
$servidor = Join-Path $aqui "servidor"

if (-not (Test-Path $ngrok)) {
    Write-Error "ngrok nao encontrado em $ngrok."
    exit 1
}

# Sem chave de leitura o endereco publico fica aberto para qualquer um.
$env_arquivo = Join-Path $servidor ".env"
$chave = ""
if (Test-Path $env_arquivo) {
    $linha = Select-String -Path $env_arquivo -Pattern '^MAPA_CHAVE_LEITURA=(.+)$' |
        Select-Object -First 1
    if ($linha) { $chave = $linha.Matches[0].Groups[1].Value.Trim() }
}
if (-not $chave) {
    Write-Warning "MAPA_CHAVE_LEITURA esta vazia em servidor\.env: a API ficara aberta na internet."
    Write-Warning "Gere uma chave, coloque no .env e na variavel CAMERA_API_KEY da Vercel."
    Write-Host ""
}

# O tunel so serve se houver algo escutando atras dele.
$respondeu = $false
try {
    Invoke-WebRequest -Uri "http://127.0.0.1:$Porta/api/v1/saude" -UseBasicParsing -TimeoutSec 3 | Out-Null
    $respondeu = $true
} catch {
    if ($_.Exception.Response) { $respondeu = $true }  # respondeu 401: esta no ar, so pediu chave
}
if (-not $respondeu) {
    Write-Host "A API nao esta no ar na porta $Porta. Subindo..."
    Start-Process -WorkingDirectory $servidor -FilePath $php `
        -ArgumentList "artisan", "serve", "--host=0.0.0.0", "--port=$Porta"
    Start-Sleep -Seconds 3
}

$argumentos = @("http", "$Porta")
if ($Dominio) { $argumentos = @("http", "--url=$Dominio", "$Porta") }

Write-Host ""
Write-Host "Abrindo o tunel. O endereco https aparece na tela do ngrok como 'Forwarding'."
Write-Host "Copie esse endereco para a variavel CAMERA_API_URL no painel da Vercel."
Write-Host "Feche esta janela (Ctrl+C) quando a apresentacao terminar: o endereco morre junto."
Write-Host ""

& $ngrok @argumentos
