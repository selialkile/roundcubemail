var around_puzzle = around_puzzle || {};

// Define o módulo no objeto global.
around_puzzle.mailbox_list = (function() {
  'use strict';

  function init() {
    console.log("testando init");
  }

  function update() {
    // ...
  }

  function get() {
    $.getJSON("?_task=around_puzzle&_action=mailbox_list&_remote=1", function (data){
      console.log(data['messagecount']);
    });
  }

  return {
    init:init,
    update: update
  };

}());

around_puzzle.mailbox_list.init();