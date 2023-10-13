import{_ as D}from"./Authenticated.2b161553.js";import{_ as g}from"./Button.46fd4c92.js";import{_ as I}from"./Paginator.616a8b54.js";import{_ as $,r as F}from"./SearchInput.ddb77fb3.js";import{_ as K}from"./MultiSelect.f3f3b46f.js";import{_ as h,a as _}from"./TableData.d6badb6c.js";import{_ as j}from"./TableHeadSort.fe6010b6.js";import{g as x,h as M,f as u,a as t,u as i,w as s,F as b,o as c,Z as O,b as e,i as C,d as r,t as d,k as B,l as w,c as U,O as P}from"./app.e38e8891.js";import{r as A}from"./PlusIcon.84f12cd0.js";import{r as T}from"./BackspaceIcon.d5d84ce5.js";import{r as E}from"./PencilSquareIcon.d5a0953d.js";import{r as R}from"./TrashIcon.1c7c5479.js";import"./open-closed.22813fed.js";import"./use-resolve-button-type.cf50c916.js";import"./RectangleStackIcon.7548c8fa.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.9ef3d7a2.js";const J=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform ",-1),Z={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},q={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},z={class:"flex justify-end"},G=e("span",null," Create ",-1),H={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},Q={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},W={class:"mt-3"},X={class:"flex space-x-1"},Y=e("span",null," Search ",-1),ee=e("span",null," Reset ",-1),te={class:"flex flex-col space-y-2"},se={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ne=e("span",null,"Showing",-1),ae={class:"font-medium"},le=e("span",null,"to",-1),oe={class:"font-medium"},re=e("span",null,"of",-1),de={class:"font-medium"},ie=e("span",null,"results",-1),ce={class:"mt-6 flex flex-col"},ue={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},me={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},fe={class:"min-w-full border-separate",style:{"border-spacing":"0"}},pe={class:"bg-gray-100"},ge={class:"divide-x divide-gray-200"},_e={class:"bg-white"},xe={class:"divide-y divide-gray-200"},he={class:"flex py-1 px-3 space-x-2"},ve={class:"text-blue-700 text-md pr-2"},ye={key:0},be={class:"flex justify-center space-x-1"},we=e("span",null," Edit ",-1),Pe=e("span",null," Delete ",-1),ke={key:0},Ve=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),$e=[Ve],Re={__name:"Index",props:{deliveryProductMappings:Object},setup(a){const l=x({name:"",vend_code:"",sortKey:"",sortBy:!0,numberPerPage:100});x(!1),x(),x("");const v=x([]);M(()=>{v.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],l.value.numberPerPage=v.value[0]});function L(f){!confirm("Are you sure to delete "+f.name+"?")||P.delete("/delivery-product-mappings/"+f.id)}function y(){P.get("/delivery-product-mappings",{...l.value,numberPerPage:l.value.numberPerPage.id},{preserveState:!0,replace:!0})}function S(){P.get("/delivery-product-mappings")}function N(f){l.value.sortKey=f,l.value.sortBy=!l.value.sortBy,y()}return(f,o)=>(c(),u(b,null,[t(i(O),{title:"Delivery Platform"}),t(D,null,{header:s(()=>[J]),default:s(()=>{var k,V;return[e("div",Z,[e("div",q,[e("div",z,[t(i(C),{href:"/delivery-product-mappings/create"},{default:s(()=>[t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},{default:s(()=>[t(i(A),{class:"h-4 w-4","aria-hidden":"true"}),G]),_:1})]),_:1})]),e("div",H,[t($,{placeholderStr:"Name",modelValue:l.value.name,"onUpdate:modelValue":o[0]||(o[0]=n=>l.value.name=n)},{default:s(()=>[r(" Name ")]),_:1},8,["modelValue"]),t($,{placeholderStr:"Vend ID",modelValue:l.value.vend_code,"onUpdate:modelValue":o[1]||(o[1]=n=>l.value.vend_code=n)},{default:s(()=>[r(" Vend ID ")]),_:1},8,["modelValue"])]),e("div",Q,[e("div",W,[e("div",X,[t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[2]||(o[2]=n=>y())},{default:s(()=>[t(i(F),{class:"h-4 w-4","aria-hidden":"true"}),Y]),_:1}),t(g,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[3]||(o[3]=n=>S())},{default:s(()=>[t(i(T),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})])]),e("div",te,[e("p",se,[ne,e("span",ae,d((k=a.deliveryProductMappings.meta.from)!=null?k:0),1),le,e("span",oe,d((V=a.deliveryProductMappings.meta.to)!=null?V:0),1),re,e("span",de,d(a.deliveryProductMappings.meta.total),1),ie]),t(K,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":o[4]||(o[4]=n=>l.value.numberPerPage=n),options:v.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:y},null,8,["modelValue","options"])])])]),e("div",ce,[e("div",ue,[e("div",me,[e("table",fe,[e("thead",pe,[e("tr",ge,[t(h,null,{default:s(()=>[r(" # ")]),_:1}),t(j,{modelName:"name",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:o[5]||(o[5]=n=>N("name"))},{default:s(()=>[r(" Name ")]),_:1},8,["sortKey","sortBy"]),t(h,null,{default:s(()=>[r(" Channel - Product ")]),_:1}),t(h,null,{default:s(()=>[r(" Binded Vending Machines ")]),_:1}),t(h)])]),e("tbody",_e,[(c(!0),u(b,null,B(a.deliveryProductMappings.data,(n,m)=>(c(),u("tr",{key:n.id,class:"divide-x divide-gray-200"},[t(_,{currentIndex:m,totalLength:a.deliveryProductMappings.length,inputClass:"text-center"},{default:s(()=>[r(d(a.deliveryProductMappings.meta.from+m),1)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:m,totalLength:a.deliveryProductMappings.length,inputClass:"text-left"},{default:s(()=>[r(d(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:m,totalLength:a.deliveryProductMappings.length,inputClass:"text-left"},{default:s(()=>[e("ul",xe,[(c(!0),u(b,null,B(n.deliveryProductMappingItemsJson,p=>(c(),u("li",he,[e("span",ve,d(p.channel_code),1),p.product.code?(c(),u("span",ye,d(p.product.code),1)):w("",!0),e("span",null," - "+d(p.product.name),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:m,totalLength:a.deliveryProductMappings.length,inputClass:"text-left"},{default:s(()=>[r(d(n.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(_,{currentIndex:m,totalLength:a.deliveryProductMappings.length,inputClass:"text-center"},{default:s(()=>[e("div",be,[t(i(C),{href:"/delivery-product-mappings/"+n.id+"/edit"},{default:s(()=>[t(g,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"},{default:s(()=>[t(i(E),{class:"w-4 h-4"}),we]),_:1})]),_:2},1032,["href"]),t(g,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:p=>L(n)},{default:s(()=>[t(i(R),{class:"w-4 h-4"}),Pe]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.deliveryProductMappings.data.length?w("",!0):(c(),u("tr",ke,$e))])]),a.deliveryProductMappings.data.length?(c(),U(I,{key:0,links:a.deliveryProductMappings.links,meta:a.deliveryProductMappings.meta},null,8,["links","meta"])):w("",!0)])])])])]}),_:1})],64))}};export{Re as default};