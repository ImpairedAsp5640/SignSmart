function createFallingElement(type) {
    const element = document.createElement("img");
    element.classList.add("animation-elements");

    element.src = type === "sign" ? "sign.png" : "folder-icon.png";

    element.style.left = Math.random() * (window.innerWidth - 50) + "px";

    const duration = Math.random() * 3 + 3;
    element.style.animationDuration = `${duration}s`;

    document.body.appendChild(element);

    setTimeout(() => element.remove(), duration * 1000);
}

setInterval(() => {
    createFallingElement("sign");
    createFallingElement("folder");
}, 500);
