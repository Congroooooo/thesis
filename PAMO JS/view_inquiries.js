document.querySelectorAll(".reply-btn").forEach((btn) => {
  btn.addEventListener("click", function () {
    document.getElementById("replyModal").style.display = "flex";
    document.getElementById("modalInquiryId").value = this.dataset.id;
    document.getElementById("modalReplyText").value = "";
  });
});
document.getElementById("closeReplyModal").onclick = function () {
  document.getElementById("replyModal").style.display = "none";
};
document.getElementById("replyForm").onsubmit = function (e) {
  e.preventDefault();
  const formData = new FormData(this);
  fetch("reply_inquiry.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.json())
    .then((data) => {
      if (data.success) {
        alert("Reply sent!");
        location.reload();
      } else {
        alert("Error: " + (data.error || "Could not send reply."));
      }
    });
};

function logout() {
  showLogoutConfirmation();
}
