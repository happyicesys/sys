import{_ as D}from"./Authenticated.891b50c9.js";import{_ as d}from"./Button.b17e3b5e.js";import{_ as F}from"./Paginator.212796dd.js";import{_ as K,r as j}from"./SearchInput.4db4173e.js";import{_ as I}from"./MultiSelect.52fedbb8.js";import{_ as P,a as h}from"./TableData.e82b807b.js";import{_ as O}from"./TableHeadSort.6c472328.js";import{g as c,h as A,f as g,a as t,u as i,w as s,F as w,o as u,Z as E,b as e,i as T,d as m,t as p,k as U,l as k,c as R,O as v}from"./app.a5ba100b.js";import{r as Z}from"./PlusIcon.7aa6eb40.js";import{r as q}from"./BackspaceIcon.67c581b0.js";import{r as z}from"./PencilSquareIcon.e275ce26.js";import{r as G}from"./TrashIcon.46b7cf29.js";import"./open-closed.34e7965e.js";import"./use-resolve-button-type.ceb68aa2.js";import"./RectangleStackIcon.c2c6dc58.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.562fa679.js";const H=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform ",-1),J={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},Q={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},W={class:"flex justify-end"},X=e("span",null," Create ",-1),Y={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ee={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},te={class:"mt-3"},se={class:"flex space-x-1"},oe=e("span",null," Search ",-1),ae=e("span",null," Reset ",-1),ne={class:"flex flex-col space-y-2"},le={class:"text-sm text-gray-700 leading-5 flex space-x-1"},re=e("span",null,"Showing",-1),ie={class:"font-medium"},de=e("span",null,"to",-1),ce={class:"font-medium"},ue=e("span",null,"of",-1),me={class:"font-medium"},pe=e("span",null,"results",-1),fe={class:"mt-6 flex flex-col"},ge={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},xe={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},_e={class:"min-w-full border-separate",style:{"border-spacing":"0"}},he={class:"bg-gray-100"},ve={class:"divide-x divide-gray-200"},ye={class:"bg-white"},be={class:"flex justify-center space-x-1"},Pe=e("span",null," Edit ",-1),we=e("span",null," Delete ",-1),ke={key:0},$e=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ce=[$e],Re={__name:"Index",props:{deliveryProductMappings:Object},setup(o){const a=c({name:"",sortKey:"",sortBy:!0,numberPerPage:100}),$=c(!1),C=c(),B=c(""),x=c([]);A(()=>{x.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=x.value[0]});function V(r){!confirm("Are you sure to delete "+r.name+"?")||v.delete("/delivery-product-mappings/"+r.id)}function M(r){B.value="update",C.value=r,$.value=!0}function _(){v.get("/delivery-product-mappings",{...a.value,numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function N(){v.get("/delivery-product-mappings")}function S(r){a.value.sortKey=r,a.value.sortBy=!a.value.sortBy,_()}return(r,n)=>(u(),g(w,null,[t(i(E),{title:"Delivery Platform"}),t(D,null,{header:s(()=>[H]),default:s(()=>{var y,b;return[e("div",J,[e("div",Q,[e("div",W,[t(i(T),{href:"/delivery-product-mappings/create"},{default:s(()=>[t(d,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},{default:s(()=>[t(i(Z),{class:"h-4 w-4","aria-hidden":"true"}),X]),_:1})]),_:1})]),e("div",Y,[t(K,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":n[0]||(n[0]=l=>a.value.name=l)},{default:s(()=>[m(" Name ")]),_:1},8,["modelValue"])]),e("div",ee,[e("div",te,[e("div",se,[t(d,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[1]||(n[1]=l=>_())},{default:s(()=>[t(i(j),{class:"h-4 w-4","aria-hidden":"true"}),oe]),_:1}),t(d,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[2]||(n[2]=l=>N())},{default:s(()=>[t(i(q),{class:"h-4 w-4","aria-hidden":"true"}),ae]),_:1})])]),e("div",ne,[e("p",le,[re,e("span",ie,p((y=o.deliveryProductMappings.meta.from)!=null?y:0),1),de,e("span",ce,p((b=o.deliveryProductMappings.meta.to)!=null?b:0),1),ue,e("span",me,p(o.deliveryProductMappings.meta.total),1),pe]),t(I,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":n[3]||(n[3]=l=>a.value.numberPerPage=l),options:x.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:_},null,8,["modelValue","options"])])])]),e("div",fe,[e("div",ge,[e("div",xe,[e("table",_e,[e("thead",he,[e("tr",ve,[t(P,null,{default:s(()=>[m(" # ")]),_:1}),t(O,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:n[4]||(n[4]=l=>S("name"))},{default:s(()=>[m(" Name ")]),_:1},8,["sortKey","sortBy"]),t(P)])]),e("tbody",ye,[(u(!0),g(w,null,U(o.deliveryProductMappings.data,(l,f)=>(u(),g("tr",{key:l.id,class:"divide-x divide-gray-200"},[t(h,{currentIndex:f,totalLength:o.deliveryProductMappings.length,inputClass:"text-center"},{default:s(()=>[m(p(o.deliveryProductMappings.meta.from+f),1)]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:f,totalLength:o.deliveryProductMappings.length,inputClass:"text-left"},{default:s(()=>[m(p(l.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(h,{currentIndex:f,totalLength:o.deliveryProductMappings.length,inputClass:"text-center"},{default:s(()=>[e("div",be,[t(d,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:L=>M(l)},{default:s(()=>[t(i(z),{class:"w-4 h-4"}),Pe]),_:2},1032,["onClick"]),t(d,{type:"button",class:"bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1",onClick:L=>V(l)},{default:s(()=>[t(i(G),{class:"w-4 h-4"}),we]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),o.deliveryProductMappings.data.length?k("",!0):(u(),g("tr",ke,Ce))])]),o.deliveryProductMappings.data.length?(u(),R(F,{key:0,links:o.deliveryProductMappings.links,meta:o.deliveryProductMappings.meta},null,8,["links","meta"])):k("",!0)])])])])]}),_:1})],64))}};export{Re as default};
