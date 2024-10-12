<?php
include '../../db/connection.php';
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to check if a table exists
function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result->num_rows > 0;
}

// Create books table if not exists
if (!tableExists($conn, 'books')) {
    $sql = "CREATE TABLE books (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql)) {
        die("Error creating books table: " . $conn->error);
    }
}

// Create notes table if not exists
if (!tableExists($conn, 'notes')) {
    $sql = "CREATE TABLE notes (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        book_id INT(6) UNSIGNED,
        content TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
    )";
    if (!$conn->query($sql)) {
        die("Error creating notes table: " . $conn->error);
    }
}

// Create book_tags table if not exists
if (!tableExists($conn, 'book_tags')) {
    $sql = "CREATE TABLE book_tags (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE
    )";
    if (!$conn->query($sql)) {
        die("Error creating book_tags table: " . $conn->error);
    }
}

// Create note_book_tags table if not exists
if (!tableExists($conn, 'note_book_tags')) {
    $sql = "CREATE TABLE note_book_tags (
        note_id INT(6) UNSIGNED,
        book_tag_id INT(6) UNSIGNED,
        PRIMARY KEY (note_id, book_tag_id),
        FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
        FOREIGN KEY (book_tag_id) REFERENCES book_tags(id) ON DELETE CASCADE
    )";
    if (!$conn->query($sql)) {
        die("Error creating note_book_tags table: " . $conn->error);
    }
}

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_book':
                $title = $_POST['title'];
                $author = $_POST['author'];
                $sql = "INSERT INTO books (title, author) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ss", $title, $author);
                $stmt->execute();
                $stmt->close();
                break;

            case 'add_note':
                $book_id = $_POST['book_id'];
                $content = $_POST['content'];
                $book_tags = isset($_POST['book_tags']) ? $_POST['book_tags'] : [];

                // Insert note
                $sql = "INSERT INTO notes (book_id, content) VALUES (?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("is", $book_id, $content);
                $stmt->execute();
                $note_id = $stmt->insert_id;
                $stmt->close();

                // Process book tags
                foreach ($book_tags as $tag) {
                    // Check if book tag exists, if not create it
                    $sql = "INSERT INTO book_tags (name) VALUES (?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("s", $tag);
                    $stmt->execute();
                    $tag_id = $stmt->insert_id ?: $conn->query("SELECT id FROM book_tags WHERE name = '$tag'")->fetch_assoc()['id'];
                    $stmt->close();

                    // Link book tag to note
                    $sql = "INSERT INTO note_book_tags (note_id, book_tag_id) VALUES (?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("ii", $note_id, $tag_id);
                    $stmt->execute();
                    $stmt->close();
                }
                break;
        }
    }
}

// Fetch books
$books_result = $conn->query("SELECT * FROM books ORDER BY title");

// Fetch book tags
$book_tags_result = $conn->query("SELECT * FROM book_tags ORDER BY name");

// Fetch notes with books and book tags
$filter_book = isset($_GET['filter_book']) ? $_GET['filter_book'] : '';
$filter_book_tags = isset($_GET['filter_book_tags']) ? $_GET['filter_book_tags'] : [];

$notes_query = "SELECT n.id, n.content, n.created_at, b.title AS book_title, b.author AS book_author, 
                GROUP_CONCAT(bt.name) AS book_tags
                FROM notes n
                JOIN books b ON n.book_id = b.id
                LEFT JOIN note_book_tags nbt ON n.id = nbt.note_id
                LEFT JOIN book_tags bt ON nbt.book_tag_id = bt.id";

if ($filter_book || !empty($filter_book_tags)) {
    $notes_query .= " WHERE 1=1";
    if ($filter_book) {
        $notes_query .= " AND b.id = $filter_book";
    }
    if (!empty($filter_book_tags)) {
        $tag_ids = implode(',', array_map('intval', $filter_book_tags));
        $notes_query .= " AND n.id IN (SELECT note_id FROM note_book_tags WHERE book_tag_id IN ($tag_ids))";
    }
}

$notes_query .= " GROUP BY n.id ORDER BY n.created_at DESC";
$notes_result = $conn->query($notes_query);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Notes Management</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-8">Book Notes Management</h1>
        
        <!-- Add New Book Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Add New Book</h2>
            <form method="post" action="" class="space-y-4">
                <input type="hidden" name="action" value="add_book">
                <div>
                    <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                    <input type="text" id="title" name="title" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <div>
                    <label for="author" class="block text-sm font-medium text-gray-700">Author</label>
                    <input type="text" id="author" name="author" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                </div>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">Add Book</button>
            </form>
        </div>
        
        <!-- Add New Note Form -->
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-xl font-semibold mb-4">Add New Note</h2>
            <form method="post" action="" class="space-y-4">
                <input type="hidden" name="action" value="add_note">
                <div>
                    <label for="book_id" class="block text-sm font-medium text-gray-700">Book</label>
                    <select id="book_id" name="book_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <?php while ($book = $books_result->fetch_assoc()): ?>
                            <option value="<?php echo $book['id']; ?>"><?php echo htmlspecialchars($book['title'] . ' by ' . $book['author']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="content" class="block text-sm font-medium text-gray-700">Note Content</label>
                    <textarea id="content" name="content" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50" rows="4"></textarea>
                </div>
                <div>
                    <label for="book_tags" class="block text-sm font-medium text-gray-700">Note Tags</label>
                    <select id="book_tags" name="book_tags[]" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <?php 
                        $book_tags_result->data_seek(0);
                        while ($tag = $book_tags_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo htmlspecialchars($tag['name']); ?>"><?php echo htmlspecialchars($tag['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">Add Note</button>
            </form>
        </div>
        
        <!-- Notes List with Filtering -->
        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Notes</h2>
            <form method="get" action="" class="mb-4 space-y-4">
                <div>
                    <label for="filter_book" class="block text-sm font-medium text-gray-700">Filter by Book</label>
                    <select id="filter_book" name="filter_book" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <option value="">All Books</option>
                        <?php 
                        $books_result->data_seek(0);
                        while ($book = $books_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $book['id']; ?>" <?php echo ($filter_book == $book['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($book['title']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label for="filter_book_tags" class="block text-sm font-medium text-gray-700">Filter by Book Tags</label>
                    <select id="filter_book_tags" name="filter_book_tags[]" multiple class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                        <?php 
                        $book_tags_result->data_seek(0);
                        while ($tag = $book_tags_result->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $tag['id']; ?>" <?php echo in_array($tag['id'], $filter_book_tags) ? 'selected' : ''; ?>><?php echo htmlspecialchars($tag['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <button type="submit" class="bg-indigo-500 text-white px-4 py-2 rounded-lg hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Apply Filters</button>
            </form>
            <div class="space-y-4">
                <?php while ($note = $notes_result->fetch_assoc()): ?>
                    <div class="border rounded-lg p-4">
                        <h3 class="font-semibold"><?php echo htmlspecialchars($note['book_title']); ?> by <?php echo htmlspecialchars($note['book_author']); ?></h3>
                        <p class="text-gray-600 text-sm"><?php echo date('F j, Y, g:i a', strtotime($note['created_at'])); ?></p>
                        <p class="mt-2"><?php echo nl2br(htmlspecialchars($note['content'])); ?></p>
                        <?php if ($note['book_tags']): ?>
                            <div class="mt-2">
                                <?php foreach (explode(',', $note['book_tags']) as $tag): ?>
                                    <span class="inline-block bg-gray-200 rounded-full px-3 py-1 text-sm font-semibold text-gray-700 mr-2 mb-2"><?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#book_tags, #filter_book_tags').select2({
                tags: true,
                tokenSeparators: [',', ' '],
                placeholder: "Select or add tags"
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>