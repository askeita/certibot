document.addEventListener('DOMContentLoaded', function() {
    const resultsPage = 10;
    const resultRowsNodeList = document.querySelectorAll('.result-row');
    const resultRows = Array.from(resultRowsNodeList);
    const totalResults = resultRows.length;
    const totalPages = Math.ceil(totalResults / resultsPage) ;

    let currentPage = 1;

    const paginationElement = document.getElementById('resultsPagination');
    const nextPageButton = document.getElementById('nextPage');
    // Adds links and sets up the pagination
    for (let i = 1; i <= totalPages; i++) {
        const pageItem = document.createElement('li');
        pageItem.classList.add('page-item');
        if (i === 1) {
            pageItem.classList.add('active');
        }

        const pageLink = document.createElement('a');
        pageLink.classList.add('page-link');
        pageLink.href = '#';
        pageLink.textContent = i;
        pageLink.dataset.page = i;

        pageLink.addEventListener('click', function(e) {
            e.preventDefault();
            goToPage(i);
        });

        pageItem.appendChild(pageLink);
        paginationElement.insertBefore(pageItem, nextPageButton);
    }

    showPage(1);
    // Event listener for the "Previous page" button
    document.getElementById('prevPage')?.addEventListener('click', function(e) {
        e.preventDefault();
        if (currentPage > 1) {
            goToPage(currentPage - 1);
        }
    });

    // Event listener for the "Next page" button
    document.getElementById('nextPage')?.addEventListener('click', function(e) {
        e.preventDefault();
        if (currentPage < totalPages) {
            goToPage(currentPage + 1);
        }
    });

    // Function to go to a specific page
    function goToPage(pageNumber) {
        currentPage = pageNumber;
        showPage(currentPage);

        const pageItems = paginationElement.querySelectorAll('.page-item');
        pageItems.forEach(item => {
            item.classList.remove('active');
            const link = item.querySelector('.page-link');
            if (link && link.dataset.page === pageNumber) {
                item.classList.add('active');
            }
        });

        const prevPage = document.getElementById('prevPage');
        const nextPage = document.getElementById('nextPage');

        if (pageNumber === 1) {
            prevPage.classList.add('disabled');
        } else {
            prevPage.classList.remove('disabled');
        }

        if (pageNumber === totalPages) {
            nextPage.classList.add('disabled');
        } else {
            nextPage.classList.remove('disabled');
        }
    }

    // Function to show the results for the current page
    function showPage(pageNumber) {
        const startIndex = (pageNumber - 1) * resultsPage;
        const endIndex = Math.min(startIndex + resultsPage - 1, totalResults - 1);

        resultRows.forEach((row, index) => {
            if (index >= startIndex && index < endIndex + 1) {
                row.classList.add('visible');
            } else {
                row.classList.remove('visible');
            }
        });
    }
})