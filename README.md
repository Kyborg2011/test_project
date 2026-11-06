# File Storage

Веб-приложение для хранения PDF- и DOCX-файлов с ограниченным сроком жизни.

- Асинхронная (AJAX) загрузка файлов через веб-интерфейс с ограничением размера.
- Страница управления: список загруженных файлов, скачивание, ручное удаление.
- Автоматическое удаление файлов через заданное время (по умолчанию 24 часа).
- После каждого удаления (ручного или автоматического) в RabbitMQ публикуется сообщение «отправь письмо» на адрес из `.env`.

> Проект в разработке. Ход работы и принятые решения — в [docs/PLAN.md](docs/PLAN.md).

## Стек

| Компонент | Версия |
|---|---|
| PHP | 8.4 (php-fpm) |
| Laravel | 13 |
| MySQL | 8.4 |
| RabbitMQ | 4.1 (+ management UI) |
| nginx | 1.28 |
| Frontend | Bootstrap 5.3 + jQuery 3.7 (локальные файлы, без сборки) |

## Требования

- Docker с плагином Docker Compose v2.
- GNU Make.
- Пользователь должен иметь доступ к Docker без `sudo` (входить в группу `docker`).

## Запуск

```bash
make init
```

Команда:
1. создаёт `.env` из `.env.example` (если его ещё нет);
2. собирает PHP-образ и поднимает контейнеры;
3. ставит Composer-зависимости, генерирует `APP_KEY`, выполняет миграции.

После запуска:

| Что | Адрес |
|---|---|
| Веб-интерфейс | http://localhost:8080 |
| RabbitMQ management | http://localhost:15672 (логин/пароль — `RABBITMQ_USER` / `RABBITMQ_PASSWORD`, по умолчанию `app` / `secret`) |

Порты меняются в `.env` (`APP_PORT`, `RABBITMQ_MANAGEMENT_PORT`).

## Команды

| Команда | Описание |
|---|---|
| `make up` / `make down` | Запустить / остановить контейнеры |
| `make ps`, `make logs` | Статус и логи контейнеров |
| `make shell` | Shell в PHP-контейнере |
| `make test` | Тесты (PHPUnit, отдельная БД `testing`) |
| `make lint` / `make fix` | Проверка / исправление code style (PSR-12) |
| `make analyse` | Статический анализ (PHPStan, level 8) |
| `make check` | lint + analyse + test |
| `make artisan c="..."` | Произвольная artisan-команда |
| `make composer c="..."` | Произвольная composer-команда |

## Контейнеры

| Сервис | Назначение |
|---|---|
| `nginx` | HTTP, статика, лимит размера запроса |
| `app` | PHP-FPM, обработка HTTP-запросов |
| `scheduler` | Laravel Scheduler (`schedule:work`) — периодические задачи |
| `mysql` | Основная БД + БД `testing` для тестов |
| `rabbitmq` | Брокер сообщений |

## Конфигурация

Все настройки задаются в `.env` (см. комментарии в [.env.example](.env.example)).

| Переменная | По умолчанию | Описание |
|---|---|---|
| `APP_PORT` | `8080` | Порт веб-интерфейса на хосте |
| `HTTP_MAX_BODY_SIZE` | `12M` | Жёсткий лимит тела запроса для nginx и PHP. Должен быть больше лимита размера файла |
| `HOST_UID` / `HOST_GID` | `1000` | Пользователь, от которого работают PHP-контейнеры (Makefile подставляет текущего) |
| `FILES_DISK` | `local` | Диск хранения файлов (`config/filesystems.php`) |
| `FILES_DIRECTORY` | `uploads` | Каталог внутри диска |
| `FILES_MAX_SIZE_KB` | `10240` | Максимальный размер одного файла, КБ (10 МБ) |
| `FILES_ALLOWED_EXTENSIONS` | `pdf,docx` | Допустимые расширения через запятую. Для каждого должны быть описаны MIME-типы в [config/files.php](config/files.php) |
| `FILES_TTL_MINUTES` | `1440` | Срок жизни файла до автоудаления, минуты (24 часа) |
| `FILES_PER_PAGE` | `20` | Файлов на странице управления |
| `FILES_PURGE_BATCH_SIZE` | `100` | Сколько просроченных файлов обрабатывается за один запрос к БД |
| `NOTIFICATION_EMAIL` | `admin@example.com` | Получатель писем об удалении файлов |

Настройки файлов проверяются при старте ([FileSettings](app/Services/Files/FileSettings.php)): некорректное значение (например, `FILES_TTL_MINUTES=0` или расширение без MIME-типов) сразу приводит к понятной ошибке.

## Загрузка файлов

Страница `/`: выбор одного файла, AJAX-отправка (`POST /files`) с прогресс-баром, результат — без перезагрузки страницы.

Проверки на сервере ([UploadFileRequest](app/Http/Requests/UploadFileRequest.php), [AllowedFileType](app/Rules/AllowedFileType.php)):
- размер — не больше `FILES_MAX_SIZE_KB`;
- расширение — из `FILES_ALLOWED_EXTENSIONS`;
- реальный MIME-тип по содержимому файла (libmagic) должен соответствовать расширению: текстовый файл или ZIP, переименованные в `.pdf`/`.docx`, отклоняются.

Хранение ([FileUploadService](app/Services/Files/FileUploadService.php)):
- файл сохраняется в приватное хранилище (`storage/app/private/uploads`) под случайным именем (UUID), недоступен напрямую по URL;
- исходное имя очищается (пути, управляющие символы, длина) и хранится в БД;
- сначала пишется файл, затем запись в БД; если запись в БД не удалась — файл удаляется.

Ответы API: `201` — файл загружен (JSON с данными файла), `422` — ошибка валидации, `413` — запрос больше `HTTP_MAX_BODY_SIZE` (отсекается nginx), `419` — устаревший CSRF-токен.

## Удаление файлов

### Жизненный цикл файла

```
 загрузка                         expires_at = время загрузки + FILES_TTL_MINUTES
    │
    ▼
┌──────────┐  истёк срок   ┌───────────────────┐  files:purge-expired  ┌─────────┐
│ доступен ├──────────────►│ истёк, ждёт       ├──────────────────────►│ удалён  │
└────┬─────┘               │ автоудаления      │  (≤ ~1 мин)           └─────────┘
     │                     └───────────────────┘                            ▲
     │  кнопка «Delete» на странице управления                              │
     └──────────────────────────────────────────────────────────────────────┘
```

| Состояние | В БД | Для пользователя |
|---|---|---|
| Доступен | `deleted_at IS NULL`, `expires_at > now` | Виден в списке, скачивается, можно удалить |
| Истёк, ждёт автоудаления | `deleted_at IS NULL`, `expires_at <= now` | Уже не виден и не скачивается; удаляется ближайшим запуском автоудаления |
| Удалён | `deleted_at`, `deletion_reason` = `manual` / `expired` | Не виден. Файла на диске нет, запись остаётся в БД как история |

### Способы удаления

**Вручную.** Страница «Files» (`/files`) → кнопка «Delete» → подтверждение → AJAX-запрос `DELETE /files/{id}`:
- `200` — файл удалён, строка исчезает из таблицы;
- `404` — файла нет: уже удалён (например, автоудалением секундой раньше) или срок истёк.

**Автоматически.** Контейнер `scheduler` раз в минуту запускает команду `files:purge-expired` ([PurgeExpiredFiles](app/Console/Commands/PurgeExpiredFiles.php)):
- находит файлы с `expires_at <= now`, ещё не удалённые;
- обрабатывает их пачками по `FILES_PURGE_BATCH_SIZE`;
- ошибка с одним файлом не останавливает остальные — такой файл будет обработан при следующем запуске;
- запуски не накладываются друг на друга (`withoutOverlapping`);
- если scheduler был остановлен, при старте удаляются все накопившиеся просроченные файлы.

Запуск вручную: `make artisan c="files:purge-expired"`.

### Что происходит при удалении

Оба способа используют один сервис — [FileDeletionService](app/Services/Files/FileDeletionService.php):

1. **Транзакция в БД:**
   - запись помечается удалённой атомарным `UPDATE stored_files SET deleted_at = …, deletion_reason = … WHERE id = … AND deleted_at IS NULL`. Если строка уже удалена (обновлено 0 строк), сервис ничего не делает;
   - в таблицу `outbox_messages` пишется сообщение «отправь письмо» ([transactional outbox](https://microservices.io/patterns/data/transactional-outbox.html)).
2. **После коммита** файл удаляется с диска.
3. **Доставка уведомления** (отдельные процессы, см. ниже): `outbox_messages` → RabbitMQ → consumer → письмо.

### Доставка уведомления

```
outbox_messages ──► outbox:relay ──► exchange "notifications" ──► queue "notifications.email" ──► notifications:consume ──► Laravel Mail
   (MySQL)          (контейнер relay)        (RabbitMQ, routing key "email.send")                (контейнер consumer)      (MAIL_MAILER=log →
                                                                                                                           storage/logs/mail.log)
```

- **Relay** ([RelayOutbox](app/Console/Commands/RelayOutbox.php)) постоянно забирает неотправленные сообщения пачками по `OUTBOX_BATCH_SIZE` и публикует их в RabbitMQ с подтверждением от брокера (publisher confirms). Сообщение помечается отправленным (`published_at`) только после подтверждения. При ошибке публикации — повтор с растущей паузой: `OUTBOX_RETRY_BASE_SECONDS`, ×2 на каждую попытку, не больше `OUTBOX_RETRY_MAX_SECONDS`.
- **Сообщения и очереди** durable/persistent — переживают перезапуск RabbitMQ.
- **Consumer** ([ConsumeNotifications](app/Console/Commands/ConsumeNotifications.php)) читает очередь, собирает письмо ([NotificationEmail](app/Mail/NotificationEmail.php)) и отправляет его через Laravel Mail, затем подтверждает сообщение (`ack`). Реальной SMTP-отправки нет: транспорт `log` записывает письмо целиком в `storage/logs/mail.log`. Для SMTP достаточно поменять `MAIL_MAILER` и `MAIL_*` в `.env`.
- **Дубликаты.** Доставка «как минимум один раз»: в редких сбоях сообщение может быть опубликовано повторно. Consumer отбрасывает уже обработанные `message_id`.
- **Невалидное сообщение** или ошибка отправки письма — сообщение отклоняется (`reject`) и попадает в очередь `notifications.email.dead` (dead letter queue), где его можно посмотреть в RabbitMQ UI.

Формат сообщения — в [docs/PLAN.md](docs/PLAN.md#3-контракт-сообщения-rabbitmq), текст письма — шаблон [resources/views/messages/file-deleted.blade.php](resources/views/messages/file-deleted.blade.php).

### Гарантии и нештатные ситуации

| Ситуация | Что происходит |
|---|---|
| Пользователь и автоудаление удаляют файл одновременно | Удаляет только один; уведомление одно. Второй получает `404` / пропускает файл |
| RabbitMQ недоступен в момент удаления | Удаление проходит. Сообщение ждёт в `outbox_messages`, relay повторяет публикацию, пока брокер не вернётся |
| Ошибка БД при удалении (в т. ч. при записи в outbox) | Транзакция откатывается: файл остаётся доступным и на диске, уведомления нет. Пользователь видит ошибку; автоудаление повторит на следующем запуске |
| Файл не удалось стереть с диска | Удаление не откатывается (файл уже недоступен, уведомление гарантировано). В лог пишется `warning` с путём файла |
| Файла на диске уже нет | Не ошибка, удаление проходит штатно |
| Scheduler был остановлен | При старте удаляет все накопившиеся просроченные файлы |
| Consumer остановлен | Сообщения копятся в очереди RabbitMQ и обрабатываются после запуска |
| Повторная доставка сообщения | Consumer игнорирует уже обработанный `message_id` |

### Настройки удаления

| Переменная | По умолчанию | Описание |
|---|---|---|
| `FILES_TTL_MINUTES` | `1440` | Срок жизни файла, минуты. Фиксируется при загрузке: новое значение действует только на новые файлы |
| `FILES_PURGE_BATCH_SIZE` | `100` | Размер пачки автоудаления |
| `NOTIFICATION_EMAIL` | `admin@example.com` | Получатель писем |
| `OUTBOX_BATCH_SIZE` | `50` | Сколько сообщений relay публикует за один проход |
| `OUTBOX_POLL_INTERVAL_MS` | `1000` | Пауза relay, когда отправлять нечего |
| `OUTBOX_RETRY_BASE_SECONDS` / `OUTBOX_RETRY_MAX_SECONDS` | `5` / `300` | Пауза перед повтором неудачной публикации: начальная и максимальная |
| `RABBITMQ_EXCHANGE`, `RABBITMQ_ROUTING_KEY`, `RABBITMQ_QUEUE` | `notifications`, `email.send`, `notifications.email` | Топология RabbitMQ |
| `RABBITMQ_DEAD_LETTER_EXCHANGE`, `RABBITMQ_DEAD_LETTER_QUEUE` | `notifications.dlx`, `notifications.email.dead` | Куда попадают отклонённые сообщения |
| `MAIL_MAILER`, `MAIL_LOG_CHANNEL` | `log`, `mail` | Транспорт писем; при `log` письма пишутся в `storage/logs/mail.log` |

### Как проверить

1. В `.env` поставить `FILES_TTL_MINUTES=2` (вернуть `1440` после проверки).
2. Загрузить файл на главной странице.
3. Удалить другой файл вручную на странице «Files».
4. Через 2–3 минуты:
   - загруженный файл исчез из списка и из `storage/app/private/uploads`;
   - в `storage/logs/mail.log` — два письма: «… has been deleted (deleted manually)» и «… (expired)».

### Где смотреть

| Что | Где |
|---|---|
| События удаления (`File deleted.`, ошибки) | `storage/logs/laravel.log` |
| Отправленные письма | `storage/logs/mail.log` |
| Работа процессов | `make logs` (сервисы `scheduler`, `relay`, `consumer`) |
| Очереди и сообщения | RabbitMQ UI: http://localhost:15672 → Queues |
| Неотправленные сообщения | `make artisan c="db:table outbox_messages"` или SQL: `SELECT * FROM outbox_messages WHERE published_at IS NULL` |

## Code style

PSR-12 (Laravel Pint, пресет `psr12`), `declare(strict_types=1)` во всех PHP-файлах, PHPStan level 8 (Larastan).
