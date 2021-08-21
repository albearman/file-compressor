let form = document.forms.namedItem("file-compression");
form.addEventListener('submit', function (e) {
  
  let oOutput = document.querySelector("div");
  let oData = new FormData(this);
  
  let oReq = new XMLHttpRequest();
  oReq.open("POST", "compress.php", true);
  oReq.responseType = 'json';
  oReq.onload = function () {
    if (oReq.status === 200) {
      let before = Math.round(oReq.response.before / 1024);
      let after = Math.round(oReq.response.after / 1024);
      let compressProc = Math.round(100 - (oReq.response.after * 100 / oReq.response.before));
      
      oOutput.innerHTML = `Upload file size: <strong>${before}kb</strong> <br> `
        + `Compressed file size: <strong>${after}kb</strong> <br> `
        + `Compression percentage: <strong>${compressProc}%</strong>`
    } else {
      oOutput.innerHTML = "Error " + oReq.status + " occurred when trying to upload your file.<br \/>";
    }
  };
  
  oReq.send(oData);
  e.preventDefault();
}, false);
