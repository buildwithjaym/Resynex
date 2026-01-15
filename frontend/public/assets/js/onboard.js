(() => {
    const track = document.getElementById("track");
    const dots = Array.from(document.querySelectorAll("#dots .dotx"));
    const backBtn = document.getElementById("backBtn");
    const nextBtn = document.getElementById("nextBtn");
    const skipBtn = document.getElementById("skipBtn");

    const total = dots.length;
    let i = 0;

    const setIndex = (n) => {
        i = Math.max(0, Math.min(total - 1, n));
        track.style.transform = `translateX(${-i * 100}%)`;
        dots.forEach((d, idx) => d.classList.toggle("active", idx === i));
        backBtn.disabled = i === 0;
        nextBtn.textContent = i === total - 1 ? "Get started" : "Next";
    };

    const finish = async () => {
        try {
            await fetch("onboard.php", { method: "POST" });
        } catch { }
        window.location.href = "login.php";
    };

    backBtn.addEventListener("click", () => setIndex(i - 1));
    nextBtn.addEventListener("click", () => (i === total - 1 ? finish() : setIndex(i + 1)));
    skipBtn.addEventListener("click", finish);

    // swipe
    let startX = 0, dx = 0, down = false;

    const onDown = (x) => { down = true; startX = x; dx = 0; };
    const onMove = (x) => { if (!down) return; dx = x - startX; };
    const onUp = () => {
        if (!down) return;
        down = false;
        if (Math.abs(dx) > 45) setIndex(i + (dx < 0 ? 1 : -1));
        dx = 0;
    };

    track.addEventListener("touchstart", (e) => onDown(e.touches[0].clientX), { passive: true });
    track.addEventListener("touchmove", (e) => onMove(e.touches[0].clientX), { passive: true });
    track.addEventListener("touchend", onUp);

    track.addEventListener("mousedown", (e) => onDown(e.clientX));
    window.addEventListener("mousemove", (e) => onMove(e.clientX));
    window.addEventListener("mouseup", onUp);

    setIndex(0);
})();
