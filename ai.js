const chatInput = document.getElementById("chat-input");
const sendBtn = document.getElementById("send-btn");
const chatBody = document.getElementById("chat-body");

function addMessage(content, sender) {
    const msg = document.createElement("div");
    msg.className = "msg " + sender;
    msg.innerHTML = content;
    chatBody.appendChild(msg);
    chatBody.scrollTop = chatBody.scrollHeight;
}

sendBtn.addEventListener("click", () => {
    const text = chatInput.value.trim();
    if (!text) return;

    addMessage(text, "user");
    chatInput.value = "";

    fetch("/api/ai-chat.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message: text })
    })
    .then(res => res.json())
    .then(data => {
        const aiText = data.choices[0].message.content;
        addMessage(aiText, "ai");
    })
    .catch(err => {
        addMessage("⚠ Error: Could not reach AI server.", "ai");
    });
});

chatInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter") sendBtn.click();
});
