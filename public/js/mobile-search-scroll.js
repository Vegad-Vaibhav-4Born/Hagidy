let lastScrollTop = 0;
const searchBar = document.querySelector('.mobile-search-stick-positon');

window.addEventListener('scroll', function() {
    let scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    if (scrollTop > lastScrollTop && scrollTop > 100) {
        searchBar.classList.add('hide');
    } else {
        searchBar.classList.remove('hide');
    }
    
    lastScrollTop = scrollTop;
});