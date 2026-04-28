# TP1 - Wally Street (Seminario PHP - UNLP)

### Integrantes:
Matias Lissalde
Valentín Lafourcade
Paul Falcon

### Librerías Externas Utilizadas
* **Slim Framework 4**: Núcleo del Backend para la gestión de rutas de la API.
* **Slim Body Parsing Middleware**: Para procesar peticiones JSON (necesario para el frontend en React).
* **PHP-View / PSR-7**: Manejo de Request/Response.

### Instalación y Configuración
1. **Dependencias:** Abrir una terminal en la carpeta del proyecto y ejecutar `composer install`.
2. **Base de Datos:** Importar el archivo `seminariophp.sql` en phpMyAdmin.
3. **Servidor:** El proyecto está configurado para correr en Apache (XAMPP). Se debe acceder a través de la carpeta `/public`.

### Funcionalidades Implementadas
* Registro e inicio de sesión de usuarios.
* Consulta de activos financieros en tiempo real.
* Compra y venta de activos con validación de saldo.
* Historial de transacciones.