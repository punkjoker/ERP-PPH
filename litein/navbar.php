<!-- litein_store_navbar.php -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<div class="w-64 h-screen bg-blue-100 fixed left-0 top-0 p-5 overflow-y-auto flex flex-col">
  <!-- Profile Section -->
  <div class="flex items-center mb-6">
    <div class="w-12 h-12 bg-blue-500 text-white flex items-center justify-center rounded-full text-xl font-bold">
      <i class="fa-solid fa-store"></i>
    </div>
    <div class="ml-3">
      <p class="font-semibold text-blue-800">Litein Store</p>
      <a href="profile.php" class="text-sm text-blue-600 hover:underline"><i class="fa-solid fa-pen-to-square mr-1"></i>Edit Profile</a>
    </div>
  </div>

  <!-- Store B Nav Links -->
  <nav class="space-y-6 flex-1">
    <div>
      <h3 class="text-blue-700 font-semibold uppercase">Store B</h3>
      <ul class="ml-4 space-y-2 text-sm">
        <li><a href="store_b_chemicals_in.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>Chemicals In</a></li>
        <li><a href="store_b_chemicals_inventory.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-vials mr-2"></i>Chemical Inventory</a></li>
        <li><a href="store_b_engineering_products_in.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>Engineering Products In</a></li>
        <li><a href="store_b_engineering_products_inventory.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-boxes mr-2"></i>Engineering Products Inventory</a></li>
        <li><a href="store_b_finished_products_in.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>Finished Products In</a></li>
        <li><a href="store_b_finished_products_inventory.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-boxes mr-2"></i>Finished Products Inventory</a></li>
        <li><a href="create_store_b_order.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-file-alt mr-2"></i>Create Order</a></li>
        <li><a href="all_orders_store_b.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-clipboard-list mr-2"></i>All Orders</a></li>
        <li><a href="store_b_order_items.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-box mr-2"></i>View Order Items</a></li>
        <li><a href="store_b_order_deliveries.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-truck mr-2"></i>Order Deliveries</a></li>
        <li><a href="store_b_delivery_details.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-info-circle mr-2"></i>Delivery Details</a></li>
        <li><a href="store_b_reports.php" class="block hover:bg-blue-200 p-2 rounded"><i class="fa-solid fa-chart-pie mr-2"></i>Reports</a></li>
      </ul>
    </div>
  </nav>

  <!-- Logout Button -->
  <div class="mt-auto">
    <a href="logout.php" class="block bg-red-500 hover:bg-red-600 text-white text-center py-2 rounded">
      <i class="fa-solid fa-right-from-bracket mr-2"></i>Logout
    </a>
  </div>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.querySelector(".overflow-y-auto");

    // Restore previous scroll position
    const savedScroll = localStorage.getItem("sidebar-scroll");
    if (savedScroll) {
      sidebar.scrollTop = parseInt(savedScroll, 10);
    }

    // Save scroll position whenever user scrolls
    sidebar.addEventListener("scroll", function () {
      localStorage.setItem("sidebar-scroll", sidebar.scrollTop);
    });
  });
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const sidebar = document.querySelector(".overflow-y-auto");

  // Restore scroll position
  const savedScroll = localStorage.getItem("sidebar-scroll");
  if (savedScroll) sidebar.scrollTop = parseInt(savedScroll, 10);

  sidebar.addEventListener("scroll", function () {
    localStorage.setItem("sidebar-scroll", sidebar.scrollTop);
  });

  // Highlight active link
  const currentPage = window.location.pathname.split("/").pop();
  const navLinks = document.querySelectorAll("nav a");

  navLinks.forEach(link => {
    const linkPage = link.getAttribute("href");

    if (linkPage === currentPage) {
      link.classList.add("bg-blue-500", "text-white", "font-semibold");
      link.classList.remove("hover:bg-blue-200");
    } else {
      link.classList.remove("bg-blue-500", "text-white", "font-semibold");
    }
  });
});
</script>

</div>
