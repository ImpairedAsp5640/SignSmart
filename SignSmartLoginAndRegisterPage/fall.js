function createFallingElement(type) {
    const element = document.createElement("img");
    element.classList.add("animation-elements");

    element.src = type === "sign" ? "sign.png" : "folder-icon.png";

    element.style.left = Math.random() * window.innerWidth + "px";
    element.style.animationDuration = `${Math.random() * 3 + 3}s`;

    document.body.appendChild(element);

    setTimeout(() => element.remove(), 5000);
}

setInterval(() => {
    createFallingElement("sign");
    createFallingElement("folder");
}, 500);