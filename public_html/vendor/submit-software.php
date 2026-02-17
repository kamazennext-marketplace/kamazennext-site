<?php
session_start();

// Allow only logged-in vendors
if (!isset($_SESSION['vendor_id'])) {
  header("Location: /forms/vendor-login.html");
  exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Submit Software | KAMA ZENNEXT</title>
</head>

<body>

  <h2>Submit Your Software</h2>

  <form id="submitSoftwareForm" class="form-grid">
    <div class="form-group">
      <label>Software Name</label><br>
      <input type="text" name="title" required>
    </div>

    <div class="form-group">
      <label>Category</label><br>
      <select name="category" required>
        <option value="CRM">CRM</option>
        <option value="Accounting">Accounting</option>
        <option value="AI & Automation">AI & Automation</option>
        <option value="Security">Security</option>
      </select>
    </div>

    <div class="form-group">
      <label>Description</label><br>
      <textarea name="description" required></textarea>
    </div>

    <div class="form-group">
      <label>Website URL</label><br>
      <input type="url" name="website" required>
    </div>

    <div class="form-group">
      <label>Pricing</label><br>
      <input type="text" name="price" placeholder="₹ / Free / Trial">
    </div>

    <div class="form-group">
      <label>Logo / Image</label><br>
      <input type="file" name="image" accept="image/*">
    </div>

    <button type="submit" id="submitBtn">Submit Software</button>
    <div id="msg"></div>
  </form>

  <script>
    document.getElementById('submitSoftwareForm').addEventListener('submit', async function (e) {
      e.preventDefault();
      const btn = document.getElementById('submitBtn');
      const msg = document.getElementById('msg');

      btn.disabled = true;
      btn.textContent = 'Submitting...';
      msg.textContent = '';

      const formData = new FormData(this);

      try {
        const res = await fetch('/api/v1/vendor/software', {
          method: 'POST',
          body: formData // allow browser to set content-type for multipart
        });
        const data = await res.json();

        if (res.ok && data.success) {
          msg.style.color = 'green';
          msg.textContent = data.message || 'Submitted successfully!';
          this.reset();
        } else {
          msg.style.color = 'red';
          msg.textContent = data.error?.message || data.message || 'Submission failed';
        }
      } catch (err) {
        console.error(err);
        msg.style.color = 'red';
        msg.textContent = 'Network error';
      } finally {
        btn.disabled = false;
        btn.textContent = 'Submit Software';
      }
    });
  </script>

</body>

</html>