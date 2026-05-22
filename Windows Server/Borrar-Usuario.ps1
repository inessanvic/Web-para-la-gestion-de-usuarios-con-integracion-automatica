# Se define el dato que hay que pasarle al script al ejecutarlo.
# "Mandatory=$true" significa que es obligatorio
param (
    [Parameter(Mandatory=$true)] [string]$SamAccount
)

# --- Configuración del dominio ---
$DC = "DC=usuariosbiblioteca,DC=local"

# --- BORRADO DEL USUARIO ---
try {
    # Busca el usuario en el directorio con ese nombre de usuario
    # -ErrorAction SilentlyContinue hace que no se muestre un error si no lo encuentra
    $Usuario = Get-ADUser -Filter { SamAccountName -eq $SamAccount } -ErrorAction SilentlyContinue

    # Comprueba si se ha encontrado el usuario
    if (-not $Usuario) {
        Write-Error "No se ha encontrado ningún usuario con el nombre de cuenta '$SamAccount'."
    # Significa que el script no ha encontrado al usuario. Esto lo usa la API
        exit 2
    }

    # Elimina el usuario del directorio. -Confirm:$false evita una confirmación manual
    Remove-ADUser -Identity $SamAccount -Confirm:$false

    Write-Output "Usuario '$SamAccount' eliminado correctamente."
    # Significa que la ejecución del script es correcta. Esto lo usa la API para saber si está bien
    exit 0

} catch {
    Write-Error "Error al eliminar el usuario: $_"
    # Significa que la ejecución del script ha fallado. Esto lo usa la API 
    exit 3
}