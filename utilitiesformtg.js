//nodelist foreach polyfill
if (window.NodeList && !NodeList.prototype.forEach) {
  NodeList.prototype.forEach = Array.prototype.forEach;
}

function swapimage(id, front, back) {
    if (document.getElementById(id).src == front) {
        document.getElementById(id).src=back;
    } else {
        document.getElementById(id).src=front;
    }
};

//bind touch event
//TODO: check how we do this with dynamic content
setInterval(function  () {
  document.querySelectorAll(".scryfall_hover_img").forEach(function(scryFallWrapper){
    scryFallWrapper.addEventListener("touchstart", function (event) {
      event.preventDefault();
      //remove all other classes so we can actively close stuff
      document.querySelectorAll(".scryfall_hover_img").forEach(function(scryFallWrapper){
        scryFallWrapper.classList.remove("mobileDisplayScryfallImage")
      })
      //show this
      event.currentTarget.classList.add("mobileDisplayScryfallImage");
    });
  })
}, 500);

