const chatInput = document.getElementById("chat-input");
const sendBtn = document.getElementById("send-btn");
const chatBody = document.getElementById("chat-body");

let chatHistory = [];

function addMessage(content, sender) {
    const msg = document.createElement("div");
    msg.className = "msg " + sender;
    msg.innerHTML = content;
    chatBody.appendChild(msg);
    chatBody.scrollTop = chatBody.scrollHeight;
}

function sendMessage(text) {
    const payloadHistory = [...chatHistory];

    fetch("/api/api/ask-openai.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            message: text,
            history: payloadHistory
        })
    })
    .then(res => res.json())
    .then(data => {
        const aiText = data.reply || "⚠ Error: Invalid AI response.";
        chatHistory.push({ role: "assistant", content: aiText });
        addMessage(aiText, "ai");
    })
    .catch(() => {
        addMessage("⚠ Error: Could not reach AI server.", "ai");
    });

    chatHistory.push({ role: "user", content: text });
}

sendBtn.addEventListener("click", () => {
    const text = chatInput.value.trim();
    if (!text) return;

    addMessage(text, "user");
    chatInput.value = "";

    sendMessage(text);
});

chatInput.addEventListener("keypress", (e) => {
    if (e.key === "Enter") sendBtn.click();
});
