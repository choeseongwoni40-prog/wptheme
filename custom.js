// 이탈 방지 팝업 스크립트
var popupShown = sessionStorage.getItem('exitPopupShown');
var closeCount = parseInt(sessionStorage.getItem('exitPopupCloseCount')) || 0;
var scrollTriggered = false;

window.onload = function() {
    // PC: 마우스 이탈 감지
    document.onmouseout = function(e) {
        e = e || window.event;
        var y = e.clientY;
        if (y < 0 && !popupShown && closeCount < 2) {
            showPopup();
        }
    };
    
    // PC + 모바일: 뒤로가기 감지
    history.pushState(null, '', location.href);
    window.onpopstate = function() {
        if (closeCount < 2) {
            showPopup();
        }
        history.pushState(null, '', location.href);
    };
    
    // 모바일: 스크롤 60% 도달 시 팝업
    window.onscroll = function() {
        var h = document.body.scrollHeight - window.innerHeight;
        var percent = (window.scrollY / h) * 100;
        
        if (percent > 60 && !popupShown && !scrollTriggered && closeCount < 2) {
            showPopup();
            scrollTriggered = true;
        }
    };
};

function showPopup() {
    document.getElementById('exitPopup').style.display = 'flex';
}

function closePopup() {
    document.getElementById('exitPopup').style.display = 'none';
}

function closePopupAndScroll() {
    closePopup();
    var hero = document.querySelector('.hero-section');
    if (hero) {
        hero.scrollIntoView({ behavior: 'smooth' });
    }
}

function closePopupNotNow() {
    closePopup();
    popupShown = true;
    closeCount++;
    sessionStorage.setItem('exitPopupShown', 'true');
    sessionStorage.setItem('exitPopupCloseCount', closeCount);
}

// 탭 메뉴 활성화
document.addEventListener('DOMContentLoaded', function() {
    const tabs = document.querySelectorAll('.tab-link');
    const hash = window.location.hash;
    let activeTabFound = false;

    tabs.forEach(tab => {
        if (hash && tab.getAttribute('href') === hash) {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            activeTabFound = true;
        }
    });

    if (!activeTabFound) {
        const defaultActiveTab = document.querySelector('.tab-link.active');
        if (defaultActiveTab) {
            defaultActiveTab.classList.add('active');
        }
    }
});
