<?php
// test_connection.php - для LocalDB

// Для LocalDB используем это имя
$serverName = "(localdb)\\mssqllocaldb";

// Альтернативный вариант (если не работает)
// $serverName = "(localdb)\\MSSQLLocalDB";

$connectionOptions = array(
    "Database" => "VolunteerCommunity",
    "Uid" => "", // пусто для Windows-аутентификации
    "PWD" => "", // пусто для Windows-аутентификации
    "CharacterSet" => "UTF-8",
    "LoginTimeout" => 30, // увеличим таймаут для LocalDB
    "TrustServerCertificate" => true,
    "MultipleActiveResultSets" => true
);

echo "<h1>Тест подключения к MS SQL Server (LocalDB)</h1>";
echo "Подключаюсь к: <strong>" . $serverName . "</strong><br><br>";

$conn = sqlsrv_connect($serverName, $connectionOptions);

if ($conn === false) {
    echo "<p style='color: red;'>❌ Ошибка подключения!</p>";
    echo "<pre>";
    print_r(sqlsrv_errors());
    echo "</pre>";
    
    echo "<h3>Диагностика:</h3>";
    
    // Проверяем, запущен ли LocalDB
    echo "<h4>Проверка статуса LocalDB:</h4>";
    $output = shell_exec('sqllocaldb info mssqllocaldb');
    echo "<pre>" . $output . "</pre>";
    
    if (strpos($output, 'Running') === false) {
        echo "<p>⚠️ LocalDB не запущен. Запустите его командой:</p>";
        echo "<code>sqllocaldb start mssqllocaldb</code>";
    }
} else {
    echo "<p style='color: green;'>✅ Подключение к LocalDB успешно!</p>";
    
    // Проверяем наличие базы данных
    $sql = "SELECT name FROM sys.databases WHERE name = 'VolunteerCommunity'";
    $stmt = sqlsrv_query($conn, $sql);
    $db_exists = sqlsrv_has_rows($stmt);
    
    if ($db_exists) {
        echo "<p style='color: green;'>✅ База данных VolunteerCommunity найдена!</p>";
        
        // Показываем таблицы
        $sql = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE'";
        $stmt = sqlsrv_query($conn, $sql);
        
        echo "<h4>Таблицы в базе:</h4>";
        echo "<ul>";
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            echo "<li>" . $row['TABLE_NAME'] . "</li>";
        }
        echo "</ul>";
        
        // Количество событий
        $sql = "SELECT COUNT(*) as count FROM Events";
        $stmt = sqlsrv_query($conn, $sql);
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        echo "<p>Количество событий: " . $row['count'] . "</p>";
        
    } else {
        echo "<p style='color: red;'>❌ База данных VolunteerCommunity не найдена в этом экземпляре LocalDB</p>";
    }
    
    sqlsrv_close($conn);
}

// Полезная информация
echo "<h3>Полезные команды для LocalDB:</h3>";
echo "<ul>";
echo "<li><code>sqllocaldb info mssqllocaldb</code> - информация об экземпляре</li>";
echo "<li><code>sqllocaldb start mssqllocaldb</code> - запустить LocalDB</li>";
echo "<li><code>sqllocaldb stop mssqllocaldb</code> - остановить LocalDB</li>";
echo "<li><code>sqllocaldb create mssqllocaldb</code> - создать экземпляр</li>";
echo "</ul>";
?>