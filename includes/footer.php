      </div> <!-- container-fluid end -->
    </div> <!-- page-content-wrapper end -->
  </div> <!-- wrapper end -->

  <!-- Stylish Footer -->
  <footer class="mt-4 py-3 bg-gradient" style="background: linear-gradient(135deg,#4e73df,#1cc88a); color:#fff; text-align:center; border-top-left-radius:15px; border-top-right-radius:15px; box-shadow: 0 -2px 10px rgba(0,0,0,0.1);">
      <div class="container">
          <small>&copy; <?= date('Y') ?> Smart POS | Developed by Brishab Chalise</small>
      </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Sidebar Toggle
    document.getElementById("menu-toggle").onclick = function() {
      document.getElementById("wrapper").classList.toggle("toggled");
    };
  </script>
</body>
</html>
