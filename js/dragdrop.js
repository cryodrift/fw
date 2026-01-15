import {send, write} from "/eventhandler.js";

export function init(elem, url, target) {
   let draggedItem = null;

   elem.querySelectorAll('[draggable=true]').forEach(el => el.addEventListener("dragstart", (e) => {
      if (e.target.hasAttribute('draggable')) {
         draggedItem = e.target
         e.target.classList.add("dragging")
      }
   }))

   elem.addEventListener("dragover", (e) => {
      if (draggedItem) {
         e.preventDefault()
         const afterElement = getDragAfterElement(elem, e.clientY)
         if (afterElement == null) {
            elem.appendChild(draggedItem)
         } else {
            elem.insertBefore(draggedItem, afterElement)
         }
      }
   })

   elem.addEventListener("dragend", async (e) => {
      e.target.classList.remove("dragging")
      draggedItem = null
      if (url) {
         const items = elem.querySelectorAll('[draggable=true]')
         const coll = {collection: [items]}
         // console.log('dragdrop', coll, items)
         await send(coll, 'collection', 'id', url)
         write(coll, 'collection', target)
      }
   })
}

function getDragAfterElement(container, y) {
   const draggableElements = [...container.querySelectorAll("li:not(.dragging)")];

   return draggableElements.reduce(
      (closest, child) => {
         const box = child.getBoundingClientRect();
         const offset = y - box.top - box.height / 2;
         if (offset < 0 && offset > closest.offset) {
            return {offset: offset, element: child};
         } else {
            return closest;
         }
      },
      {offset: Number.NEGATIVE_INFINITY}
   ).element;
}
