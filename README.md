# TP1 - Wally Street (Seminario PHP - UNLP)

## Integrantes
- Matias Lissalde
- Paul Falcon

## Librerías externas utilizadas
- **Slim Framework 4**: framework principal para manejar rutas y middlewares de la API.
- **PSR-7 / Slim PSR-7**: implementación de request/response compatible con Slim.
- **Slim Body Parsing Middleware**: permite leer correctamente el body de peticiones JSON.
- **vlucas/phpdotenv**: carga variables de entorno desde el archivo `.env` con las credenciales de la base de datos.

> Nota: esta librería no se instala manualmente; se descarga con `composer install` junto con el resto de dependencias.

## Instalación
1. Instalar dependencias de PHP con Composer:

```bash
composer install
```

2. Importar la base de datos desde `seminariophp.sql` en phpMyAdmin o MySQL.
3. Verificar la configuración de la conexión en el archivo `.env`.
    - Variables mínimas esperadas: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_PORT`.
    - En este proyecto el archivo `.env` sí se utiliza para cargar la conexión a la base de datos.
4. Levantar el proyecto con Apache/XAMPP y acceder al directorio `public`.

## Cómo probar la API
La API se puede probar en cualquier máquina que tenga:
- PHP 8+
- MySQL/MariaDB
- Composer
- Apache o un servidor local equivalente

No hace falta instalar librerías manualmente una por una: `composer install` se encarga de todas las dependencias externas.

### Prueba manual con Postman
1. Hacer `POST /login` para obtener el token.
2. Copiar el token desde el header `Authorization: Bearer <token>`.
3. En las rutas protegidas, enviar el header `Authorization` con ese token.
4. Probar los endpoints de usuarios, assets, trade, portfolio y transactions.

### Prueba rápida con curl
```bash
curl -i -X POST http://127.0.0.1:8080/mi-proyecto/public/login \
    -H "Content-Type: application/json" \
    -d '{"email":"test1@example.com","password":"Abc12345!"}'
```

## Funcionalidades implementadas
- Registro e inicio de sesión de usuarios.
- Consulta de activos financieros.
- Compra y venta de activos con validación de saldo.
- Historial de transacciones.