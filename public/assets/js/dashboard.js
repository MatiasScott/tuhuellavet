(function () {
    document.body.classList.add('dashboard-loaded');

    var cards = document.querySelectorAll('.tvg-kpi-card');
    cards.forEach(function (card, idx) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(6px)';
        setTimeout(function () {
            card.style.transition = 'opacity 240ms ease, transform 240ms ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, idx * 45);
    });

    var canvas = document.getElementById('dashboardTrendChart');
    if (!canvas) {
        return;
    }
    var dataSource = canvas.getAttribute('data-points') || '';
    var points = dataSource.split(',').map(function (item) {
        var parsed = parseFloat(item);
        return Number.isFinite(parsed) ? parsed : 0;
    });
    if (!points.length) {
        return;
    }

    var ctx = canvas.getContext('2d');
    if (!ctx) {
        return;
    }

    var width = canvas.clientWidth || 600;
    var height = canvas.clientHeight || 180;
    canvas.width = width;
    canvas.height = height;

    var max = Math.max.apply(null, points);
    var min = Math.min.apply(null, points);
    var range = Math.max(max - min, 1);
    var padX = 20;
    var padY = 16;

    ctx.clearRect(0, 0, width, height);

    ctx.strokeStyle = '#d9e6ee';
    ctx.lineWidth = 1;
    for (var g = 0; g <= 3; g += 1) {
        var gy = padY + (height - padY * 2) * (g / 3);
        ctx.beginPath();
        ctx.moveTo(padX, gy);
        ctx.lineTo(width - padX, gy);
        ctx.stroke();
    }

    ctx.beginPath();
    points.forEach(function (value, index) {
        var x = padX + ((width - padX * 2) * index / Math.max(points.length - 1, 1));
        var y = height - padY - ((value - min) / range) * (height - padY * 2);
        if (index === 0) {
            ctx.moveTo(x, y);
        } else {
            ctx.lineTo(x, y);
        }
    });
    ctx.strokeStyle = '#0d6f83';
    ctx.lineWidth = 3;
    ctx.stroke();

    ctx.lineTo(width - padX, height - padY);
    ctx.lineTo(padX, height - padY);
    ctx.closePath();
    ctx.fillStyle = 'rgba(13, 111, 131, 0.12)';
    ctx.fill();
})();
