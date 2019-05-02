function swapimage(id, front, back) {
    if (document.getElementById(id).src == front) {
        document.getElementById(id).src=back;        
    } else {
        document.getElementById(id).src=front;
    }
};