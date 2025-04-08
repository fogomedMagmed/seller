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

// Если форма подтверждения отправлена
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Начинаем транзакцию
        $pdo->beginTransaction();
        
        // Удаляем изображение товара, если оно есть
        if (!empty($product['image']) && file_exists('../' . $product['image'])) {
            unlink('../' . $product['image']);
        }
        
        // Удаляем товар из базы данных
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
        $stmt->execute([$productId, $user['id']]);
        
        // Завершаем транзакцию
        $pdo->commit();
        
        $_SESSION['seller_success'] = 'Товар успешно удален';
        header('Location: dashboard.php');
        exit;
    } catch (PDOException $e) {
        // Откатываем транзакцию в случае ошибки
        $pdo->rollBack();
        
        $_SESSION['seller_error'] = 'Ошибка при удалении товара: ' . $e->getMessage();
        header('Location: dashboard.php');
        exit;
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
    
    <div class="bg-zinc-900 border border-zinc-800 rounded-lg p-6 max-w-lg mx-auto">
        <h1 class="text-2xl font-bold mb-6 text-center text-white">Удаление товара</h1>
        
        <div class="text-center mb-6">
            <div class="w-32 h-32 mx-auto bg-zinc-800 rounded-lg overflow-hidden mb-4">
                <img src="/<?php echo $product['image']; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="w-full h-full object-cover">
            </div>
            <h2 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($product['name']); ?></h2>
            <p class="text-zinc-400 mb-2">Категория: <?php echo htmlspecialchars($product['category_name']); ?></p>
            <p class="text-zinc-400 mb-4">Цена: <?php echo $product['price']; ?> ₽</p>
            <p class="text-red-400 mb-4">Вы действительно хотите удалить этот товар? Это действие нельзя отменить.</p>
        </div>
        
        <div class="flex justify-center space-x-4">
            <a href="/seller/dashboard.php" class="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-white rounded-md transition-colors">
                Отмена
            </a>
            <form method="post" action="">
                <input type="hidden" name="confirm_delete" value="1">
                <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-md transition-colors">
                    Удалить
                </button>
            </form>
        </div>
    </div>
</div>

<?php
// Подключаем подвал сайта
include '../includes/footer.php';
?>

