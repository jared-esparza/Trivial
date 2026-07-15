<?php

declare(strict_types=1);

final class ApiRouter
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method) . ' ' . $path] = $handler;
    }

    public function dispatch(ApiRequest $request): ApiResponse
    {
        $handler = $this->routes[strtoupper($request->method) . ' ' . $request->path] ?? null;
        if ($handler === null) {
            return $this->error(404, 'NOT_FOUND', 'Ruta no encontrada.');
        }

        try {
            $response = $handler($request);
            if (!$response instanceof ApiResponse) {
                throw new LogicException('El controlador debe devolver ApiResponse.');
            }

            return $response;
        } catch (ApiException $e) {
            return $this->error($e->status, $e->errorCode, $e->getMessage(), $e->fields);
        } catch (InvalidArgumentException $e) {
            return $this->error(422, 'VALIDATION_ERROR', $e->getMessage());
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'TOO_MANY_ATTEMPTS') {
                return $this->error(429, 'TOO_MANY_ATTEMPTS', 'Demasiados intentos. Prueba de nuevo mas tarde.');
            }
            if ($e->getMessage() === 'ACCOUNT_DISABLED') {
                return $this->error(403, 'ACCOUNT_DISABLED', 'La cuenta esta desactivada.');
            }
            if ($e->getMessage() === 'PACK_FORBIDDEN') {
                return $this->error(403, 'PACK_FORBIDDEN', 'No tienes acceso a este pack.');
            }
            if ($e->getMessage() === 'COLOR_SCHEME_FORBIDDEN') {
                return $this->error(403, 'COLOR_SCHEME_FORBIDDEN', 'No tienes acceso a este esquema de colores.');
            }
            if ($e->getMessage() === 'PACK_INCOMPLETE') {
                return $this->error(422, 'PACK_INCOMPLETE', 'El pack necesita preguntas en sus seis categorias.');
            }
            if ($e->getMessage() === 'LAST_ADMIN') {
                return $this->error(409, 'LAST_ADMIN', 'No puedes eliminar al ultimo administrador activo.');
            }
            if ($e->getMessage() === 'DEFAULT_PACK_REQUIRED') {
                return $this->error(409, 'DEFAULT_PACK_REQUIRED', 'El pack Clasico es necesario como valor por defecto.');
            }
            if ($e->getMessage() === 'DEFAULT_COLOR_SCHEME_REQUIRED') {
                return $this->error(409, 'DEFAULT_COLOR_SCHEME_REQUIRED', 'El esquema Clasico es necesario como valor por defecto.');
            }
            throw $e;
        }
    }

    private function error(int $status, string $code, string $message, array $fields = []): ApiResponse
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return new ApiResponse(['error' => $error], $status);
    }
}
