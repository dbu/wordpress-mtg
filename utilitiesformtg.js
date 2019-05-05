function swapimage(id, front, back) {
    if (document.getElementById(id).src == front) {
        document.getElementById(id).src=back;
    } else {
        document.getElementById(id).src=front;
    }
};

//bind touch event
//TODO: check how we do this with dynamic content
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
