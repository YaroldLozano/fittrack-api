# fitness-api

API REST en PHP puro (sin framework) que sirve de backend a la app
**FitTrack** (Ionic + Angular). Router propio, autenticación por JWT, MySQL.

Repositorio hermano del frontend: ver el proyecto Ionic/Angular y su
documentación completa (objetivo de la app, modelo de datos, capturas,
evidencia de uso de IA) en
[fittrack-app](https://github.com/YaroldLozano/fittrack-app).

## Estructura

```
config/           Carga de .env y arreglo de configuración
routes.php        Definición de todas las rutas (router propio, sin framework)
index.php         Punto de entrada (CORS, dispatch del router)
src/
  Controllers/    Reciben el Request, llaman al Service, responden JSON
  Services/       Lógica de negocio
  Models/         Acceso a datos (PDO)
  Core/           Request, Response, Router, Jwt, Database
  Middleware/     AuthMiddleware (valida el JWT)
  Config/         Constantes (reglas de gamificación, etc.)
```

## Setup

1. Requiere PHP 8+, MySQL/MariaDB y Composer (p. ej. vía XAMPP).
2. `composer install` (instala `firebase/php-jwt` y `phpmailer/phpmailer`).
3. Crear la base de datos `Yarold` con el esquema descrito en
   [`docs/modelo-datos.md`](https://github.com/YaroldLozano/fittrack-app/blob/master/docs/modelo-datos.md)
   del repo del frontend.
4. Copiar `.env.example` a `.env` y completar:
   - Credenciales de MySQL (`DB_*`)
   - `JWT_SECRET` (cualquier cadena larga aleatoria)
   - `ANTHROPIC_API_KEY` (opcional, solo para la herramienta "Ajustar con
     IA" del frontend — se obtiene en console.anthropic.com)
5. Servir esta carpeta con Apache en `http://localhost/fitness-api`
   (el frontend espera esa URL por defecto, ver `App/src/environments/`).

## Endpoints

Ver `routes.php` para el listado completo. Agrupados por dominio: `/auth`,
`/exercises`, `/routines`, `/workouts`, `/muscle-groups`, `/progress`,
`/goals`, `/body-metrics`, `/friends`, `/posts`, `/stories`, `/messages`,
`/notifications`, `/ranking`, `/challenges`, `/workouts/group`,
`/achievements`, `/media`, `/ai/suggest-workout` (herramienta de IA), y
`/exercises/external` (proxy a la API pública de
[wger.de](https://wger.de) para explorar/importar ejercicios — ver
`App/docs/api-externa-ejercicios.md`).
