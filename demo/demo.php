<?php include_once($_SERVER['DOCUMENT_ROOT'] . implode('/', array_slice(explode('/', dirname($_SERVER['PHP_SELF'])), 0, 4)) . '/Utils.php') ?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>Demo: MVC</title>
		<link rel="stylesheet" href="style.css" media="screen">
		<link rel="stylesheet" href="../../../resources/style/demo.css" media="screen">
		<?php Utils::printClaroCss() ?>
		<style>
			#container div {
				cursor: pointer;
				padding: 5px;
			}
		</style>
	</head>
	<body>
		<h1>Demo: MVC</h1>
		<h2>Store Contents</h2>
		<div id="container"></div>

		<p>Click any item above to load it into the editor below.</p>
		<form id="form">
			<div>Name:
				<input type="text" name="name" />
			</div>
			<div>Quantity:
				<input type="text" name="quantity" />
			</div>
			<div>Category:
				<input type="text" name="category" />
			</div>
		</form>
		<button id="sell">Sell One</button><button id="save">Save</button>

		<h2>Other Actions</h2>
		<button id="add">Add Shoes</button>
		<button id="put-negative">Put Negative Quantity (should cause validation error)</button>

		<?php
			Utils::printDojoScript();
		?>
		<script>
			var inventoryStore, masterStore, cacheStore, results, currentProduct;
			require([
				"dojo/on",
				"dojo/dom",
				"dojo/dom-construct",
				"dojo/query",
				"dojo/_base/Deferred",
				"dojo/store/JsonRest",
				"dojo/store/Memory",
				"dojo/store/Cache",
				"dojo/store/Observable",
				"dojo/Stateful",
				"dojo/domReady!"
			], function(on, dom, domConstruct, query, Deferred, JsonRest, Memory, Cache, Observable, Stateful){

				function viewResults(results){
					var container = dom.byId("container");
					var rows = [];

					// results object provides a forEach method for iteration
					results.forEach(insertRow);

					results.observe(function(item, removedIndex, insertedIndex){
						// this will be called any time an item is added, removed, and updates
						if(removedIndex > -1){
							removeRow(removedIndex);
						}
						if(insertedIndex > -1){
							insertRow(item, insertedIndex);
						}
					}, true); // we can indicate to be notified of object updates as well

					function insertRow(item, i){
						var row = domConstruct.create("div", {
							className: "item",
							innerHTML: item.name + " quantity: " + item.quantity
						});
						row.itemId = item.name;

						rows.splice(i, 0, container.insertBefore(row, rows[i] || null));
					}

					function removeRow(i){
						domConstruct.destroy(rows.splice(i, 1)[0]);
					}
				}
				function viewInForm(object, form){
					// copy initial values into form inputs
					for(var i in object){
						updateInput(i, null, object.get(i));
					}
					// watch for any future changes in the object
					object.watch(updateInput);

					function updateInput(name, oldValue, newValue){
						var input = query("input[name=" + name + "]", form)[0];
						if(input){
							input.value = newValue;
						}
					}
				}

				cacheStore = new Memory({ idProperty: "name" });
				masterStore = new JsonRest({
					target: "data/",
					idProperty: "name",
					// in order to do client side caching properly, the master store needs to be aware of the query engine to client side filtering
					queryEngine: cacheStore.queryEngine,
					// a client side impl of the query we send to the server, just allows everything (unfiltered) here
					"some-query": function(){
						return true;
					},
					put: function(object, options){
						// override this because there's no backend to handle it
						return object;
					}
				});
				masterStore = Observable(masterStore);
				inventoryStore = Cache(masterStore, cacheStore, {});

				var oldPut = inventoryStore.put;
				inventoryStore.put = function(object, options){
					if(object.quantity < 0){
						throw new Error("quantity must not be negative");
					}
					// now call the original
					oldPut.call(this, object, options);
				};

				results = inventoryStore.query("some-query");
				viewResults(results);

				on(dom.byId("add"), "click", function(){
					inventoryStore.add({
						name: "Shoes",
						category: "Clothing",
						quantity: 40
					});
				});

				on(dom.byId("put-negative"), "click", function(){
					try{
						inventoryStore.put({
							name: "Donuts",
							category: "Food",
							quantity: -1
						});
					}catch(e){
						alert(e);
					}
				});

				on(dom.byId("sell"), "click", function(){
					currentProduct && currentProduct.set("quantity", currentProduct.quantity - 1);
					save();
				});
				function save(){
					if(!currentProduct){ return; }

					for(var i in currentProduct){
						if(typeof currentProduct[i] != "function" && currentProduct.hasOwnProperty(i)){
							console.log(i);
							currentProduct[i] = query("#form input[name=" + i + "]")[0].value;
						}
					}
					inventoryStore.put(currentProduct);
				}
				on(dom.byId("save"), "click", save);

				on(dom.byId("container"), ".item:click", function(evt){
					Deferred.when(inventoryStore.get(this.itemId), function(item){
						viewInForm(currentProduct = new Stateful(item), dom.byId("form"));
					});
				});
			});
		</script>
	</body>
</html>
