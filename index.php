<?php
// Устанавливаем кодировку
header('Content-Type: text/html; charset=UTF-8');

// Параметры подключения к БД
$user = 'u82258';
$pass = '7574471';
$dbname = 'u82258';

// Список допустимых языков (для валидации)
$allowedLanguages = [
    'Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Python',
    'Java', 'Haskel', 'Clojure', 'Prolog', 'Scala', 'Go'
];

// Функция для безопасного вывода данных в HTML
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Массивы для хранения ошибок и старых данных
$errors = [];
$old = [];

// Обработка POST-запроса (когда форму отправили)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Получаем данные
    $fio = trim($_POST['fio'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $languages = $_POST['languages'] ?? [];
    $biography = trim($_POST['biography'] ?? '');
    $contract = isset($_POST['contract']) ? 1 : 0;

    // Сохраняем введённые данные для повторного показа формы
    $old = compact('fio', 'phone', 'email', 'birth_date', 'gender', 'languages', 'biography', 'contract');

    // --- Валидация ---

    // 1. ФИО
    if (empty($fio)) {
        $errors['fio'] = 'Заполните ФИО.';
    } elseif (strlen($fio) > 150) {
        $errors['fio'] = 'ФИО не должно превышать 150 символов.';
    } elseif (!preg_match('/^[a-zA-Zа-яА-ЯёЁ\s\-]+$/u', $fio)) {
        $errors['fio'] = 'ФИО должно содержать только буквы, пробелы и дефисы.';
    }

    // 2. Телефон
    if (empty($phone)) {
        $errors['phone'] = 'Заполните телефон.';
    } elseif (!preg_match('/^[\d\s\(\)\+\-]+$/', $phone)) {
        $errors['phone'] = 'Телефон содержит недопустимые символы.';
    }

    // 3. Email
    if (empty($email)) {
        $errors['email'] = 'Заполните e-mail.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный e-mail.';
    }

    // 4. Дата рождения
    if (empty($birth_date)) {
        $errors['birth_date'] = 'Укажите дату рождения.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$date || $date->format('Y-m-d') !== $birth_date) {
            $errors['birth_date'] = 'Неверный формат даты (используйте ГГГГ-ММ-ДД).';
        } else {
            $today = new DateTime();
            if ($date > $today) {
                $errors['birth_date'] = 'Дата рождения не может быть в будущем.';
            }
        }
    }

    // 5. Пол
    if (!in_array($gender, ['male', 'female'])) {
        $errors['gender'] = 'Выберите пол.';
    }

    // 6. Языки программирования
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        foreach ($languages as $lang) {
            if (!in_array($lang, $allowedLanguages)) {
                $errors['languages'] = 'Выбран недопустимый язык программирования.';
                break;
            }
        }
    }

    // 7. Биография: не более 500 символов (можно и без проверки)
    if (strlen($biography) > 500) {
        $errors['biography'] = 'Биография не должна превышать 500 символов.';
    }

    // 8. Согласие с контрактом
    if (!$contract) {
        $errors['contract'] = 'Необходимо ознакомиться с контрактом.';
    }

    // Если ошибок нет – сохраняем в БД
    if (empty($errors)) {
        try {
            // Подключение к БД
            $pdo = new PDO("mysql:host=localhost;dbname=$dbname;charset=utf8", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Начинаем транзакцию
            $pdo->beginTransaction();

            // 1. Вставляем данные в таблицу applications
            $stmt = $pdo->prepare("
                INSERT INTO applications (fio, phone, email, birth_date, gender, biography, contract_accepted)
                VALUES (:fio, :phone, :email, :birth_date, :gender, :biography, :contract)
            ");
            $stmt->execute([
                ':fio' => $fio,
                ':phone' => $phone,
                ':email' => $email,
                ':birth_date' => $birth_date,
                ':gender' => $gender,
                ':biography' => $biography,
                ':contract' => $contract
            ]);
            $applicationId = $pdo->lastInsertId();

            // 2. Вставляем связи с языками
            $stmtLang = $pdo->prepare("
                INSERT INTO application_languages (application_id, language_id)
                VALUES (:app_id, (SELECT id FROM programming_languages WHERE name = :lang_name))
            ");
            foreach ($languages as $lang) {
                $stmtLang->execute([
                    ':app_id' => $applicationId,
                    ':lang_name' => $lang
                ]);
            }

            // Фиксируем транзакцию
            $pdo->commit();

            // Перенаправляем на ту же страницу с параметром save=1
            header('Location: ?save=1');
            exit;

        } catch (PDOException $e) {
            // Если ошибка, откатываем транзакцию
            $pdo->rollBack();
            $errors['db'] = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }
}

// Если запрос GET и есть параметр save, показываем сообщение об успехе
$successMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['save'])) {
    $successMessage = 'Данные успешно сохранены!';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Анкета</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h1>Форма для регистрации</h1>

        <?php if ($successMessage): ?>
            <div class="success"><?= h($successMessage) ?></div>
        <?php endif; ?>

        <?php if (!empty($errors) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="errors">
                <strong>Исправьте следующие ошибки:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="field">
                <label for="fio">ФИО *</label>
                <input type="text" id="fio" name="fio" value="<?= h($old['fio'] ?? '') ?>" required>
                <?php if (isset($errors['fio'])): ?>
                    <span class="error"><?= h($errors['fio']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="phone">Телефон *</label>
                <input type="tel" id="phone" name="phone" value="<?= h($old['phone'] ?? '') ?>" required>
                <?php if (isset($errors['phone'])): ?>
                    <span class="error"><?= h($errors['phone']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="email">E-mail *</label>
                <input type="email" id="email" name="email" value="<?= h($old['email'] ?? '') ?>" required>
                <?php if (isset($errors['email'])): ?>
                    <span class="error"><?= h($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="birth_date">Дата рождения *</label>
                <input type="date" id="birth_date" name="birth_date" value="<?= h($old['birth_date'] ?? '') ?>" required>
                <?php if (isset($errors['birth_date'])): ?>
                    <span class="error"><?= h($errors['birth_date']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label>Пол *</label>
                <label><input type="radio" name="gender" value="male" <?= (($old['gender'] ?? '') === 'male') ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= (($old['gender'] ?? '') === 'female') ? 'checked' : '' ?>> Женский</label>
                <?php if (isset($errors['gender'])): ?>
                    <span class="error"><?= h($errors['gender']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label>Любимые языки программирования *</label>
                <select name="languages[]" multiple size="6">
                    <?php foreach ($allowedLanguages as $lang): ?>
                        <option value="<?= h($lang) ?>" <?= (isset($old['languages']) && in_array($lang, $old['languages'])) ? 'selected' : '' ?>>
                            <?= h($lang) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['languages'])): ?>
                    <span class="error"><?= h($errors['languages']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label for="biography">Биография</label>
                <textarea id="biography" name="biography" rows="5"><?= h($old['biography'] ?? '') ?></textarea>
                <?php if (isset($errors['biography'])): ?>
                    <span class="error"><?= h($errors['biography']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <label><input type="checkbox" name="contract" value="1" <?= (($old['contract'] ?? 0) == 1) ? 'checked' : '' ?>> Я ознакомлен с контрактом *</label>
                <?php if (isset($errors['contract'])): ?>
                    <span class="error"><?= h($errors['contract']) ?></span>
                <?php endif; ?>
            </div>

            <div class="field">
                <button type="submit">Сохранить</button>
            </div>
        </form>
    </div>
</body>
</html>
