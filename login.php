<!-- login.php -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Lynntech Chemicals and Equipments</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-blue-50 flex items-center justify-center h-screen">
  <div class="bg-white p-10 rounded-xl shadow-lg w-full max-w-md">
    <h2 class="text-2xl font-bold text-center text-blue-700 mb-6">Login</h2>

    <!-- LOGIN FORM -->
    <form action="login_process.php" method="POST" class="space-y-5">
      <!-- Email -->
      <div>
        <label class="block text-gray-700 mb-1">Email</label>
        <input type="email" name="email" required 
               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
      </div>

      <!-- Password -->
      <div>
        <label class="block text-gray-700 mb-1">Password</label>
        <input type="password" name="password" required 
               class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
      </div>

      <!-- Login Button -->
      <button type="submit" 
              class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg shadow-md transition">
        Login
      </button>

      <!-- Signup Link -->
      <div class="text-center mt-4">
        <a href="signup.php" class="text-blue-600 hover:underline">
          Don’t have an account? Sign up
        </a>
      </div>
    </form>

    <p class="text-center text-gray-500 text-sm mt-5">
      © <?php echo date('Y'); ?> Lynntech Chemicals & Equipments
    </p>
  </div>
</body>
</html>
