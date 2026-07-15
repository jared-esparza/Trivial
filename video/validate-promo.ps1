$ErrorActionPreference = 'Stop'

function Assert-True {
    param(
        [bool]$Condition,
        [string]$Message
    )

    if (-not $Condition) {
        throw $Message
    }
}

$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$designPath = Join-Path $root 'DESIGN.md'
$htmlPath = Join-Path $root 'index.html'
$audioPath = Join-Path $root 'assets\rueda-quiz-promo.wav'

Assert-True (Test-Path $designPath) 'Falta video/DESIGN.md.'
Assert-True (Test-Path $htmlPath) 'Falta video/index.html.'

$html = Get-Content -Raw $htmlPath
Assert-True ($html -match 'data-composition-id="rueda-quiz-promo"') 'Falta la composición rueda-quiz-promo.'
Assert-True ($html -match 'data-width="1920"') 'La anchura debe ser 1920.'
Assert-True ($html -match 'data-height="1080"') 'La altura debe ser 1080.'
Assert-True ($html -match 'data-duration="10"') 'La duración debe ser 10 segundos.'
Assert-True ($html -match "window\.__timelines\['rueda-quiz-promo'\]") 'Falta registrar la línea de tiempo.'
Assert-True ($html -match '¿Cuánto sabes\?') 'Falta el mensaje inicial.'
Assert-True ($html -match 'Conquista los 6 quesitos\.') 'Falta la promesa principal.'
Assert-True ($html -match 'Pon a prueba tus conocimientos') 'Falta el cierre de marca.'
Assert-True ($html -notmatch 'Math\.random|Date\.now|repeat:\s*-1') 'La animación debe ser determinista y finita.'
Assert-True (Test-Path $audioPath) 'Falta el audio de 10 segundos.'

$duration = & ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 $audioPath
Assert-True ([Math]::Abs(([double]$duration) - 10.0) -lt 0.001) 'El audio no dura exactamente 10 segundos.'

Write-Output 'PASS promo source contract'
