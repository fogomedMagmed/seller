<?php
// Начинаем сессию
session_start();

// Подключаем файл с функциями
require_once '../includes/functions.php';

// Если пользователь не авторизован или не является продавцом, перенаправляем на главную
if (!isLoggedIn() || !isSeller()) {
    header('Location: ../index.php');
    exit;
}

// Подключаемся к базе данных
$pdo = require '../config/database.php';

// Получаем данные текущего пользователя
$user = getCurrentUser();

// Получаем ID товара из URL
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Проверяем, что ID товара передан
if ($productId === 0) {
    $_SESSION['seller_error'] = 'Не указан ID товара';
    header('Location: dashboard.php');
    exit;
}

// Получаем данные товара и проверяем, что он принадлежит текущему продавцу
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM products p
    JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.seller_id = ?
");
$stmt->execute([$productId, $user['id']]);
$product = $stmt->fetch();

if (!$product) {
    $_SESSION['seller_error'] = 'Товар не найден или не принадлежит вам';
    header('Location: dashboard.php');
    exit;
}

// Получаем все категории для выбора
$categories = getAllCategories($pdo);

$error = '';
$success = '';

// Обработка формы обновления товара
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;
    $category_id = $_POST['category_id'] ?? 0;
    $status = $_POST['status'] ?? 'active';
    
    // Проверяем, что все поля заполнены
    if (empty($name) || empty($description) || empty($price) || empty($category_id)) {
        $error = 'Все поля обязательны для заполнения';
    } else {
        try {
            // Начинаем транзакцию
            $pdo->beginTransaction();
            
            // Проверяем, загружено ли новое изображение
            $image = $product['image']; // По умолчанию оставляем текущее изображение
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Загружаем новое изображение
                $uploadResult = uploadImage($_FILES['image']);
                
                if ($uploadResult['success']) {
                    // Удаляем старое изображение, если оно есть
                    if (!empty($product['image']) && file_exists('../' . $product['image'])) {
                        unlink('../' . $product['image']);
                    }
                    
                    $image = $uploadResult['file_path'];
                } else {
                    throw new Exception($uploadResult['message']);
                }
            }
            
            // Обновляем товар в базе данных
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, description = ?, price = ?, image = ?, category_id = ?, status = ? 
                WHERE id = ? AND seller_id = ?
            ");
            $stmt->execute([$name, $description, $price, $image, $category_id, $status, $productId, $user['id']]);
            
            // Завершаем транзакцию
            $pdo->commit();
            
            $success = 'Товар успешно обновлен';
            
            // Обновляем данные товара
            $stmt = $pdo->prepare("
                SELECT p.*, c.name as category_name
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.id = ? AND p.seller_id = ?
            ");
            $stmt->execute([$productId, $user['id']]);
            $product = $stmt->fetch();
        } catch (Exception $e) {
            // Откатываем транзакцию в случае ошибки
            $pdo->rollBack();
            $error = 'Ошибка при обновлении товара: ' . $e->getMessage();
        }
    }
}

// Подключаем шапку сайта
include '../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="mb-4">
        <a href="/seller/dashboard.php" class="text-zinc-400 hover:text-white transition-colors">
            ← Назад к панели продавца
        </a>
    </div>
    
    <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-6">
        <h1 class="text-2xl font-bold mb-6 text-center text-white">Редактирование товара</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-900/30 border border-red-800 text-red-200 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-900/30 border border-green-800 text-green-200 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="" enctype="multipart/form-data" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div class="space-y-2">
                        <label for="name" class="text-zinc-300">Название товара</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="<?php echo htmlspecialchars($product['name']); ?>"
                            required
                            class="w-full bg-zinc-800 border border-zinc-700 rounded p-2 text-white"
                        >
                    </div>
                    
                    <div class="space-y-2">
                        <label for="price" class="text-zinc-300">Цена (₽)</label>
                        <input
                            id="price"
                            name="price"
                            type="number"
                            min="1"
                            step="1"
                            value="<?php echo $product['price']; ?>"
                            required
                            class="w-full bg-zinc-800 border border-zinc-700 rounded p-2 text-white"
                        >
                    </div>
                    
                    <div class="space-y-2">
                        <label for="category_id" class="text-zinc-300">Категория</label>
                        <select
                            id="category_id"
                            name="category_id"
                            required
                            class="w-full bg-zinc-800 border border-zinc-700 rounded p-2 text-white"
                        >
                            <option value="">Выберите категорию</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="status" class="text-zinc-300">Статус</label>
                        <select
                            id="status"
                            name="status"
                            required
                            class="w-full bg-zinc-800 border border-zinc-700 rounded p-2 text-white"
                        >
                            <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Активен</option>
                            <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Неактивен</option>
                            <option value="sold" <?php echo $product['status'] === 'sold' ? 'selected' : ''; ?>>Продан</option>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="image" class="text-zinc-300">Изображение товара</label>
                        <div class="flex items-center space-x-4">
                            <div class="w-32 h-32 bg-zinc-800 rounded-lg overflow-hidden">
                                <img src="/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover">
                            </div>
                            <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed border-zinc-700 rounded-lg cursor-pointer bg-zinc-800 hover:bg-zinc-700 transition-colors">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <svg class="w-8 h-8 mb-3 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                    <p class="mb-2 text-sm text-zinc-500">
                                        <span class="font-semibold">Нажмите для загрузки</span> или перетащите файл
                                    </p>
                                    <p class="text-xs text-zinc-500">PNG, JPG или GIF (макс. 5MB)</p>
                                </div>
                                <input id="image" name="image" type="file" accept="image/*" class="hidden" />
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <label for="description" class="text-zinc-300">Описание товара</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="12"
                        required
                        class="w-full bg-zinc-800 border border-zinc-700 rounded p-2 text-white"
                    ><?php echo htmlspecialchars($product['description']); ?></textarea>
                </div>
            </div>
            
            <div class="flex justify-between">
                <a href="/seller/dashboard.php" class="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-white rounded-md transition-colors">
                    Отмена
                </a>
                <button type="submit" class="px-4 py-2 bg-white hover:bg-gray-200 text-black rounded-md transition-colors">
                    Сохранить
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Подключаем подвал сайта
include '../includes/footer.php';
?>

