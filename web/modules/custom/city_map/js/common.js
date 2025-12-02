(function ($, Drupal) {
  console.log("JS Loaded");
  let poiItems = [];

  document.addEventListener('DOMContentLoaded', function () {

    console.log("JS Loaded");

    const termLinks = document.querySelectorAll('.term-link');
    console.log("Found term links:", termLinks.length);

    termLinks.forEach(function (link) {
      link.addEventListener('click', function (e) {
        e.preventDefault();

        const linkText = link.querySelector('.linktoDet')?.textContent.trim() || '';
        const searchInput = document.querySelector('#searchkey');
        searchInput.placeholder = "Search " + linkText;

        document.querySelector('.pois-list').classList.remove('hidden');
        showLoader();

        const termId = link.dataset.tid;
        console.log("Term ID:", termId);

        fetch('/api/get-content-by-term/' + termId)
          .then(response => response.json())
          .then(data => {
            if (data.count > 0) {
              poiItems = data.items;
              renderPOICards(poiItems);
            } else {
              document.querySelector('.poi-cards').innerHTML = `<p class="text-center text-gray-500 py-4">No amenities available at the moment.</p>`;
            }
          })
          .catch(() => {
            document.getElementById('content-area').innerHTML = '<p>Error loading data.</p>';
          });
      });
    });

    // Search functionality
    document.querySelector('#searchkey').addEventListener('input', function (e) {
      const keyword = e.target.value.toLowerCase();
      const filteredItems = poiItems.filter(item => item.title.toLowerCase().includes(keyword));
      renderPOICards(filteredItems);
    });

    document.querySelector('.poi_close').addEventListener('click', function () {
      document.querySelector('.pois-list').classList.add('hidden');
      Drupal.city_map.removeMap();
    });
  });

  function renderPOICards(items) {
    const container = document.querySelector('.poi-cards');
    container.innerHTML = '';

    if (items.length === 0) {
      container.innerHTML = `<p class="text-center text-gray-500 py-4">No matching results found.</p>`;
      return;
    }

    items.forEach(item => {
      const html = `
        <div class="lists poiDetl cursor-pointer" data-poiid="${item.id}">
          <div class="grid card card-side border border-gray-300 mb-6 rounded-xl bg-white">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="col-span-5 flex flex-stretch">
                <img src="${item.image_url}" alt="${item.title}" class="w-full h-full rounded-xl object-cover">
              </div>
              <div class="col-span-7">
                <h2 class="text-base sm:text-lg md:text-xl font-bold text-gray-800 line-clamp-2 leading-snug md:leading-7">${item.title}</h2>
                <p class="text-xs sm:text-sm md:text-base text-gray-500 mb-2 line-clamp-2">${item.address}</p>
                <p class="text-xs sm:text-sm md:text-base line-clamp-3">${item.description}</p>
                <div class='flex justify-between items-center mx-1 mb-1 mt-2'>
                  <p class="text-[10px] sm:text-xs md:text-sm text-yellow-600">Timings: ${item.timings}</p>
                  <p class="text-[10px] sm:text-xs md:text-sm font-bold text-green-600">₹ ${item.price}</p>
                </div>
              </div>  
            </div>
          </div>
        </div>
      `;
      container.insertAdjacentHTML('beforeend', html);
    });

    document.querySelectorAll('.lists.poiDetl').forEach(card => {
      card.addEventListener('click', () => {
        const itemId = card.dataset.poiid;
        const item = items.find(i => i.id == itemId);
        renderPOIDetails(item);
      });
    });
  }

  function renderPOIDetails(item) {
    const container = document.querySelector('.poi-cards');
    container.innerHTML = `
      <div class="flex items-start">
        <button class="bg-gray-200 px-4 py-2 rounded back-to-list">← Back to List</button>
      </div>
      <div class="poi-full-details bg-white rounded-xl md:p-4">
        <img src="${item.image_url}" alt="${item.title}" class="w-full h-64 object-cover rounded-xl mb-4">
        <h2 class="text-xl font-bold mb-2">${item.title}</h2>
        <p class="text-sm mb-4">${item.description}</p>
        <div class="bg-gray-200 my-5 mx-1 h-px"></div>
        <div class="py-2 px-2">
        <a class="flex flex-col items-start" target="_blank" href="https://www.google.com/maps/dir/?api=1&origin=My+Location&destination=${item.latitude},${item.longitude}">
          <img src="themes/custom/engage_theme/images/CityMap/direction.svg" class="w-14 h-12 cursor-pointer" alt="Pointer">
        <p>Directions</p>
        </a>

        </div>
        <div class="bg-gray-200 my-5 mx-1 h-px"></div>
        <div class="flex gap-5 mb-3 items-center">
          <img src="themes/custom/engage_theme/images/CityMap/location.svg" class="w-4 h-5" alt="Pointer">
          <p class="text-xs">${item.address}</p>
        </div>
        <div class="flex gap-5 mb-3 items-center">
          <img src="themes/custom/engage_theme/images/CityMap/clock.svg" class="w-4 h-5" alt="Pointer">
          <p class="text-xs">${item.timings}</p>
        </div>
         <div class="flex gap-5 mb-3 items-center">
          <img src="themes/custom/engage_theme/images/CityMap/website.png" class="w-4 h-5" alt="Pointer">
          <a href="${item.website_url}" target="_blank" class="text-blue-500 underline">Visit Website</a>
        </div>
         <div class="flex gap-5 mb-3 items-center">
          <img src="themes/custom/engage_theme/images/CityMap/phone.svg" class="w-4 h-5" alt="Pointer">
          <p class="text-xs">${item.contact_number}</p>
        </div>
        
      </div>
    `;
    tmpl.Zoom.toXYcustomZoom({
      map: Drupal.gmap,
      latitude: item.latitude,
      longitude: item.longitude,
      zoom: 15,
    });
   const poiDet = {
  id: item.id,
  label_color: "#ba5100",
  img_url: 'themes/custom/engage_theme/images/CityMap/pointer.png',
  lat: item.latitude,
  lon: item.longitude,
  address: item.address,
  label: item.title,
};

function addMarker(mapPois) {
  tmpl.Overlay.create({
    map: Drupal.gmap,
    features: Array.isArray(mapPois) ? mapPois : [mapPois],
    layer: "ATMlayer",
    layerSwitcher: false,
  });

}
addMarker(poiDet);


    container.querySelector('.back-to-list').addEventListener('click', () => {
      renderPOICards(poiItems);  // Restore full list
      Drupal.city_map.removeMap();
    });
  }


  function showLoader() {
    document.querySelector('.poi-cards').innerHTML = `
      <div role="status" class="space-y-8 animate-pulse md:space-y-0 md:space-x-8 rtl:space-x-reverse md:flex md:items-center">
        <div class="flex items-center justify-center w-full h-48 bg-gray-300 rounded-sm sm:w-96 dark:bg-gray-700">
          <svg class="w-10 h-10 text-gray-200 dark:text-gray-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 20 18">
            <path d="M18 0H2a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2Zm-5.5 4a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Zm4.376 10.481A1 1 0 0 1 16 15H4a1 1 0 0 1-.895-1.447l3.5-7A1 1 0 0 1 7.468 6a.965.965 0 0 1 .9.5l2.775 4.757 1.546-1.887a1 1 0 0 1 1.618.1l2.541 4a1 1 0 0 1 .028 1.011Z"/>
          </svg>
        </div>
        <div class="w-full">
          <div class="h-2.5 bg-gray-200 rounded-full dark:bg-gray-700 w-48 mb-4"></div>
          <div class="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[480px] mb-2.5"></div>
          <div class="h-2 bg-gray-200 rounded-full dark:bg-gray-700 mb-2.5"></div>
          <div class="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[440px] mb-2.5"></div>
          <div class="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[460px] mb-2.5"></div>
          <div class="h-2 bg-gray-200 rounded-full dark:bg-gray-700 max-w-[360px]"></div>
        </div>
        <span class="sr-only">Loading...</span>
      </div>
    `;
  }


})(jQuery, Drupal);
