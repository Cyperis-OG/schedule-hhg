<?php require_once __DIR__ . '/config.php'; ?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Map View — <?= SCHEDULE_NAME ?></title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style> #map{height: calc(100vh - 60px);} </style>
</head>
<body>
  <div style="padding:8px">
    <input type="date" id="d" value="<?= date('Y-m-d') ?>">
    <button onclick="load()">Load</button>
  </div>
  <div id="map"></div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    const map = L.map('map').setView([32.7767, -96.7970], 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{ maxZoom:19 }).addTo(map);
    let group = L.layerGroup().addTo(map);
    const BASE_PATH = '<?= BASE_PATH ?>';

    async function geocode(loc){
      const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(loc)}`;
      const res = await fetch(url, {headers:{'User-Agent':'schedule-ng'}});
      const data = await res.json();
      if(data[0]){ return {lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon)}; }
      return null;
    }

    async function load(){
      group.clearLayers();
      const d = document.getElementById('d').value;
      const rows = await fetch(`${BASE_PATH}/api/jobs_by_date_geo.php?date=${d}`).then(r=>r.json());
      const tasks = rows.map(async r=>{
        let coords = null;
        if(r.lat && r.lng){
          coords = {lat:r.lat, lng:r.lng};
        }else if(r.location){
          coords = await geocode(r.location);
        }
        if(coords){
          const m = L.marker([coords.lat, coords.lng]).addTo(group);
          if(r.contractor){
            m.bindTooltip(r.contractor, {permanent:true, direction:'right'});
          }
          m.bindPopup(`<b>${r.title}</b><br>${r.location}<br>${r.contractor||''}<br>${r.time}`);
          m.on('mouseover', ()=>m.openPopup());
          m.on('mouseout', ()=>m.closePopup());
        }
      });
      await Promise.all(tasks);
      if(group.getLayers().length){ map.fitBounds(group.getBounds()); }
    }
    load();
  </script>
</body>
</html>