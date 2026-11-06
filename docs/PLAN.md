# План реализации

Веб-приложение на Laravel + PHP 8 для хранения PDF/DOCX-файлов с ограниченным сроком жизни.
Исходное ТЗ — в [CLAUDE.md](../CLAUDE.md).

## 1. Принятые решения

| # | Вопрос | Решение |
|---|---|---|
| 1 | «Асинхронный загрузчик» | AJAX-загрузка (jQuery + FormData) с прогресс-баром, без перезагрузки страницы. Сохранение на сервере синхронное. |
| 2 | Количество файлов за раз | Один файл за загрузку. |
| 3 | Лимиты | Все лимиты настраиваются через `.env`: размер файла, срок жизни (по умолчанию 24 ч), допустимые расширения, пагинация, размеры пачек и т. д. |
| 4 | Проверка типа файла | Расширение + реальный MIME по содержимому (`finfo`). Только `.pdf` и `.docx` (не `.doc`). Проверка на клиенте — только для удобства. |
| 5 | Аутентификация | Не нужна. Файлы хранятся в приватном storage и отдаются только через контроллер по id. |
| 6 | Список файлов | Имя, тип, размер, дата загрузки, оставшееся время, «Скачать», «Удалить». Пагинация. Без превью. |
| 7 | Удаление | Физический файл удаляется, запись в БД помечается `deleted_at` + `deletion_reason` (`manual` / `expired`). |
| 8 | Таймер удаления | Laravel Scheduler, команда раз в минуту. Просроченные файлы скрываются из списка и не скачиваются сразу. |
| 9 | Срок жизни | Задаётся в `.env`, по умолчанию 24 часа. Время хранится в UTC. |
| 10 | Уведомления | Одно письмо на каждый удалённый файл (ручное и автоматическое удаление). |
| 11 | Надёжность публикации | Transactional outbox: пометка «удалён» и запись сообщения — в одной транзакции; отдельный процесс публикует в RabbitMQ. |
| 12 | Гонки ручного/автоудаления | Атомарный `UPDATE ... WHERE deleted_at IS NULL`; сообщение создаётся, только если обновлена ровно 1 строка. |
| 13 | CRUD | Create = загрузка, Read = список + скачивание, Delete = удаление. Update не делаем (нет в ТЗ). |
| 14 | Email | Наша зона — опубликовать в RabbitMQ сообщение «отправь письмо» (адрес, тема, текст, данные события). Демо-consumer читает очередь, формирует письмо и «отправляет» его через Laravel Mail с транспортом `log` (без SMTP), затем делает `ack`. Переход на SMTP — смена `MAIL_MAILER` в `.env`. |
| 15 | AMQP-клиент | `php-amqplib` напрямую (одна зависимость), без Laravel-драйвера очередей: сообщение — чистый JSON, понятный любому внешнему сервису. |
| 16 | Docker | Один `docker-compose.yml` для локального запуска и тестов. Прод-харднинг вне рамок задачи. |
| 17 | Фронтенд | Bootstrap + jQuery локальными файлами в `public/vendor/`, без Node/Vite. |
| 18 | Тесты | PHPUnit: unit + feature на каждом этапе. Тестовая БД — MySQL в Docker. RabbitMQ в тестах подменяется через интерфейс. |
| 19 | Code style | PSR-12 (Laravel Pint с пресетом `psr12`), `declare(strict_types=1)` во всех PHP-файлах, PHPStan. |
| 20 | Промпты | Все промпты фиксируются в [PROMPTS.md](../PROMPTS.md). |

## 2. Этапы

| Этап | Содержание | Риски → меры |
|---|---|---|
| **0. Решения** | Этот документ, `PROMPTS.md`. | — |
| **1. Инфраструктура и каркас** | Docker Compose: `nginx`, `app` (php-fpm 8.4), `mysql`, `rabbitmq`, `scheduler`, `relay`, `consumer`. Каркас Laravel, Pint (PSR-12), PHPStan, `strict_types`, Makefile, `.env.example`. | Сервисы стартуют раньше БД/брокера → healthcheck + `depends_on: service_healthy`. Права на volume → UID/GID через build args. Лимиты загрузки nginx/php → из `.env`. |
| **2. Схема БД и модели** | Миграции `stored_files`, `outbox_messages`; модели, enum `DeletionReason`, скоуп «активные». | Медленная выборка просроченных → индекс `(deleted_at, expires_at)`. |
| **3. Загрузка** | Страница загрузки, `UploadFileRequest`, `FileUploadService`, JSON-ответ, jQuery-загрузчик с прогрессом. | DOCX может определяться как `application/zip` → белый список MIME + проверка расширения. 413 от nginx приходит HTML → отдельная обработка на фронте. CSRF в AJAX → meta-тег + `$.ajaxSetup`. |
| **4. Страница управления** | Список с пагинацией, скачивание по id, удаление через AJAX `DELETE`. | Path traversal → путь берётся только из БД. Скачивание просроченного → скоуп «активные». |
| **5. Сервис удаления + outbox** | `FileDeletionService` — единая точка для ручного и автоудаления: транзакция (пометка + outbox), после commit — удаление файла с диска. | Двойные уведомления → атомарный условный UPDATE. Ошибка удаления с диска → лог, запись остаётся помеченной. |
| **6. Автоудаление** | Команда `files:purge-expired`, `everyMinute()->withoutOverlapping()`, обработка пачками. | Scheduler не запущен → отдельный контейнер `schedule:work`. Бэклог → пачки. |
| **7. RabbitMQ** | `MessagePublisher` (интерфейс) + `AmqpPublisher` (durable exchange/queue, persistent, publisher confirms, DLX). `outbox:relay` публикует из outbox. `notifications:consume` формирует письмо и отправляет через `log`-mailer, затем `ack`. | Обрыв соединения → переподключение с backoff. Дубли (at-least-once) → `message_id` в сообщении. Невалидное сообщение → `reject` в DLQ. |
| **8. Документация и финальная проверка** | `README.md`: описание проекта, архитектура, инструкция запуска. Полный прогон тестов и линтеров. | — |

Каждый этап сопровождается тестами и проходит `make lint` + `make test`.

## 3. Контракт сообщения (RabbitMQ)

Exchange `notifications` (direct, durable) → routing key `email.send` → очередь `notifications.email` (durable, DLX → `notifications.email.dead`).
Названия настраиваются через `.env`.

```json
{
  "message_id": "uuid",
  "type": "file.deleted",
  "version": 1,
  "occurred_at": "2026-11-06T12:00:00+00:00",
  "email": {
    "to": "admin@example.com",
    "subject": "File \"report.pdf\" has been deleted",
    "body": "..."
  },
  "payload": {
    "file_id": 1,
    "original_name": "report.pdf",
    "mime_type": "application/pdf",
    "size": 12345,
    "uploaded_at": "...",
    "deleted_at": "...",
    "reason": "manual | expired"
  }
}
```

## 4. Структура проекта

```
app/
├── Console/Commands/      PurgeExpiredFiles, RelayOutbox, ConsumeNotifications
├── Enums/                 DeletionReason
├── Http/Controllers/      UploadController, FileController
├── Http/Requests/         UploadFileRequest
├── Mail/                  FileDeletedMail
├── Messaging/             Contracts/MessagePublisher, Amqp/*, Messages/*
├── Models/                StoredFile, OutboxMessage
├── Providers/
└── Services/Files/        FileUploadService, FileDeletionService
config/                    files.php, rabbitmq.php
database/migrations/
resources/views/           layouts/app, upload, files/index
public/js/, public/vendor/ свой JS, Bootstrap, jQuery
routes/                    web.php, console.php (расписание)
tests/                     Unit/, Feature/
docker/                    php/, nginx/
docker-compose.yml, Makefile, .env.example, pint.json, phpstan.neon
```

Обоснование:
- Стандартная структура Laravel без DDD-слоёв — для проекта такого размера этого достаточно, и ревьюер сразу ориентируется в коде.
- Тонкие контроллеры, логика в `Services/`. Удаление вызывается и из контроллера, и из команды — единый `FileDeletionService` исключает расхождение поведения.
- Брокер изолирован за интерфейсом `MessagePublisher` — тестируется fake-реализацией, транспорт заменяем.
- Все настройки — в `config/files.php` и `config/rabbitmq.php`, значения — из `.env`.
- Scheduler, relay и consumer — отдельные контейнеры, перезапускаются независимо.
