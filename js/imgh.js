var filename = "";

/* File drag/drop */
function createObjectURL(object) {
    return (window.URL) ? window.URL.createObjectURL(object) : window.webkitURL.createObjectURL(object);
}

function revokeObjectURL(url) {
    return (window.URL) ? window.URL.revokeObjectURL(url) : window.webkitURL.revokeObjectURL(url);
}

function handleFile() {
  if(this.files.length) {
    var image = new Image();
    image.src = createObjectURL(this.files[0]);
    filename = this.files[0].name;
    document.getElementById("mapped_image").setAttribute("src", image.src);
  }
}

function handleDragFile(file) {
  var image = new Image();
  image.src = createObjectURL(file);
  filename = file.name;
  document.getElementById("mapped_image").setAttribute("src", image.src);
}

function handleDrop(event) {
  event.preventDefault();

  if (event.dataTransfer.items) {
    // Use DataTransferItemList interface to access the file(s)
    for (var i = 0; i < event.dataTransfer.items.length; i++) {
      // If dropped items aren't files, reject them
      if (event.dataTransfer.items[i].kind === 'file') {
        var file = event.dataTransfer.items[i].getAsFile();
        handleDragFile(file);
      }
    }
  }
  removeDragData(event);
}

function removeDragData(ev) {
  if (ev.dataTransfer.items) {
    // Use DataTransferItemList interface to remove the drag data
    ev.dataTransfer.items.clear();
  } else {
    // Use DataTransfer interface to remove the drag data
    ev.dataTransfer.clearData();
  }
  //document.getElementById("drag_box").style.display = "none";
}

function handleDrag(event) {
  event.preventDefault();
  console.log('dragover');
  //document.getElementById("drag_box").style.display = "unset";
}

document.getElementById("file").addEventListener("change", handleFile, false);

document.addEventListener("drop", handleDrop, false);
document.addEventListener("dragover", handleDrag, false);

document.addEventListener("dragend", function() {
  console.log('dragend');
  //document.getElementById("drag_box").style.display = "none";
}, false);
/* End of file drag/drop */

