# --- Configuración del dominio ---
$DC             = "DC=usuariosbiblioteca,DC=local"
$OU_Provisional = "OU=Usuarios_Provisionales,$DC"
$HorasLimite    = 24

# URL del servidor Ubuntu para registrar los usuarios expirados en la base de datos
$UrlExpirar   = "http://192.168.1.130/expirar.php"
$TokenSecreto = "tokenBiblioteca2026"

# Obtiene todos los usuarios que están en la OU de provisionales
# -Properties whenCreated es necesario para traer la fecha de creación, que no viene por defecto
# Se añaden GivenName, Surname, EmailAddress y Description para enviarlos al servidor Ubuntu
$Usuarios = Get-ADUser -SearchBase $OU_Provisional -Filter * -Properties whenCreated, GivenName, Surname, EmailAddress, Description

# Si no hay ningún usuario pendiente, termina el script
if (-not $Usuarios) {
    Write-Output "No hay usuarios pendientes de validación."
    exit 0
}

# Obtiene la fecha y hora actuales para poder comparar con la fecha de creación de cada usuario
$Ahora = Get-Date

# Recorre todos los usuarios encontrados en la OU provisional
foreach ($Usuario in $Usuarios) {

    # Calcula cuántas horas han pasado desde que se creó el usuario
    $HorasTranscurridas = ($Ahora - $Usuario.whenCreated).TotalHours

    # Comprueba si han pasado más horas de las permitidas
    if ($HorasTranscurridas -ge $HorasLimite) {
        try {
            # Extrae el DNI del campo descripcion donde lo guardo Crear-Usuario.ps1
            $Descripcion = $Usuario.Description
            if (-not $Descripcion) { $Descripcion = "" }
            $DNI = ""
            if ($Descripcion -match "DNI:([^\|]+)") { $DNI = $Matches[1].Trim() }

            # Recoge los datos del usuario con comprobacion de nulos
            $Nombre    = if ($Usuario.GivenName)    { $Usuario.GivenName }    else { "" }
            $Apellidos = if ($Usuario.Surname)       { $Usuario.Surname }      else { "" }
            $Email     = if ($Usuario.EmailAddress)  { $Usuario.EmailAddress } else { "" }
            $FechaRegistro = $Usuario.whenCreated.ToString("yyyy-MM-dd HH:mm:ss")

            # Construye el JSON con los datos del usuario para enviarlo al servidor Ubuntu
            $Datos = '{"samaccount":"' + $Usuario.SamAccountName + '","nombre":"' + $Nombre + '","apellidos":"' + $Apellidos + '","email":"' + $Email + '","dni":"' + $DNI + '","fecha_registro":"' + $FechaRegistro + '"}'

            # Llama al endpoint del servidor Ubuntu para registrar el usuario como expirado
            try {
                $Headers = @{ "X-API-Token" = $TokenSecreto }
                Invoke-RestMethod -Uri $UrlExpirar -Method POST -Body $Datos -ContentType "application/json" -Headers $Headers -TimeoutSec 10
                Write-Output "Usuario '$($Usuario.SamAccountName)' registrado como expirado en la base de datos."
            } catch {
                Write-Output "Aviso: no se pudo registrar '$($Usuario.SamAccountName)' en la base de datos: $_"
            }

            # Elimina el usuario. -Confirm:$false evita una confirmación manual
            Remove-ADUser -Identity $Usuario.SamAccountName -Confirm:$false
            Write-Output "Usuario '$($Usuario.SamAccountName)' eliminado por no validarse en 24 horas."

        } catch {
            Write-Error "Error al eliminar al usuario '$($Usuario.SamAccountName)'"
        }
    } else {
        # Calcula las horas que le quedan para que expire
        $HorasRestantes = [math]::Round($HorasLimite - $HorasTranscurridas, 1)
        Write-Output "Usuario '$($Usuario.SamAccountName)' pendiente de validación. Le quedan $HorasRestantes horas."
    }
}