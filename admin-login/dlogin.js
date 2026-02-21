function back(){
  window.location.href = "/check/check.html";
}
document.getElementById("adminLoginForm").addEventListener("submit", async function (e) {
  e.preventDefault();

  const adminId = document.getElementById("adminId").value;
  const pin = document.getElementById("adminPin").value;

  try {
    const response = await fetch("http://localhost:3000/api/admin-login", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ adminId, pin }),
    });

    const data = await response.json();
    if (data.success) {
      window.location.href = "/admin copy/admin-dashboard/ddash.html";
    } else {
      alert("Invalid credentials. Try again.");
    }
  } catch (error) {
    console.error("Login error:", error);
    alert("Login failed due to server error.");
  }
});
