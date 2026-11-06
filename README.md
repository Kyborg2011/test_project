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

## Удаление файлов и уведомления

Ручное и автоматическое удаление идут через один сервис — [FileDeletionService](app/Services/Files/FileDeletionService.php):

1. В одной транзакции:
   - запись помечается удалённой (`deleted_at`, `deletion_reason` = `manual` / `expired`) атомарным `UPDATE ... WHERE deleted_at IS NULL`;
   - в таблицу `outbox_messages` пишется сообщение «отправь письмо» ([transactional outbox](https://microservices.io/patterns/data/transactional-outbox.html)).
2. После коммита файл удаляется с диска. Ошибка на этом шаге логируется, но удаление не откатывает.

Что это даёт:
- уведомление не теряется, даже если RabbitMQ недоступен в момент удаления: сообщение ждёт в outbox, его опубликует отдельный процесс;
- уведомление не уходит, если удаление не состоялось (транзакция откатилась);
- одновременное удаление одного файла пользователем и автоудалением даёт ровно одно уведомление.

Текст письма — шаблон [resources/views/messages/file-deleted.blade.php](resources/views/messages/file-deleted.blade.php), формат сообщения — в [docs/PLAN.md](docs/PLAN.md#3-контракт-сообщения-rabbitmq).

## Code style

PSR-12 (Laravel Pint, пресет `psr12`), `declare(strict_types=1)` во всех PHP-файлах, PHPStan level 8 (Larastan).
