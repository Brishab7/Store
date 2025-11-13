document.addEventListener("DOMContentLoaded", () => {
  fetch('fetch_dashboard_data.php')
    .then(res => res.json())
    .then(data => {
      document.getElementById('sales').innerText = "Rs. " + data.sales;
      document.getElementById('expenses').innerText = "Rs. " + data.expenses;
      document.getElementById('profit').innerText = "Rs. " + data.profit;
      document.getElementById('low_stock').innerText = data.low_stock + " Items";
      document.getElementById('pending').innerText = data.pending;

      // Create Chart
      const ctx = document.getElementById('salesChart');
      new Chart(ctx, {
        type: 'bar',
        data: {
          labels: ['Sales', 'Expenses', 'Profit'],
          datasets: [{
            label: 'Today’s Summary',
            data: [data.sales, data.expenses, data.profit],
            borderWidth: 1
          }]
        },
        options: {
          scales: { y: { beginAtZero: true } }
        }
      });
    })
    .catch(err => console.error("Error loading dashboard:", err));
});
