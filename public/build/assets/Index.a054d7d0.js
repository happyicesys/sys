import{_ as I}from"./Authenticated.51f8df2d.js";import{_}from"./Button.0b5b1d9a.js";import{_ as O}from"./Paginator.0d7fb970.js";import{_ as L,r as D}from"./SearchInput.8f48840f.js";import{_ as J}from"./MultiSelect.48ff9607.js";import{_ as h,a as g}from"./TableData.885e60f5.js";import{_ as B}from"./TableHeadSort.04d09add.js";import{g as x,h as F,f as u,a as t,u as m,w as n,F as v,o as d,Z as T,b as e,i as S,d as r,t as i,k as $,l as y,c as j,O as C,n as U}from"./app.8dfb483a.js";import{r as A}from"./PlusIcon.50a86d1a.js";import{r as E}from"./BackspaceIcon.1cc84e35.js";import{r as R}from"./PencilSquareIcon.38a72125.js";import{r as z}from"./TrashIcon.c87df023.js";import"./open-closed.cdb7e47f.js";import"./use-resolve-button-type.39e09ef6.js";import"./RectangleStackIcon.8d6abf1b.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.946a0653.js";const Z=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Delivery Platform ",-1),q={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},G={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},H={class:"flex justify-end"},Q=e("span",null," Create ",-1),W={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},X={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},Y={class:"mt-3"},M={class:"flex space-x-1"},ee=e("span",null," Search ",-1),te=e("span",null," Reset ",-1),se={class:"flex flex-col space-y-2"},ne={class:"text-sm text-gray-700 leading-5 flex space-x-1"},le=e("span",null,"Showing",-1),oe={class:"font-medium"},ae=e("span",null,"to",-1),re={class:"font-medium"},de=e("span",null,"of",-1),ie={class:"font-medium"},ue=e("span",null,"results",-1),ce={class:"mt-6 flex flex-col"},me={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},fe={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},ge={class:"min-w-full border-separate",style:{"border-spacing":"0"}},pe={class:"bg-gray-100"},_e={class:"divide-x divide-gray-200"},xe={class:"bg-white"},he={class:"divide-y divide-gray-200"},ve={class:"flex py-1 px-3 space-x-2"},ye={class:"text-blue-700 text-md pr-2"},be={key:0},we={class:"divide-y divide-gray-200"},ke={class:"flex py-1 px-3 space-x-2"},Be={class:"flex flex-col justify-center space-y-1"},$e=e("span",null," Edit ",-1),Ce={class:"flex space-x-1 items-center"},Ve=e("span",null," Delete ",-1),Pe={key:0},Le={key:0},Se=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ke=[Se],He={__name:"Index",props:{deliveryProductMappings:Object},setup(a){const l=x({name:"",vend_code:"",sortKey:"",sortBy:!0,numberPerPage:100});x(!1),x(),x("");const b=x([]);F(()=>{b.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],l.value.numberPerPage=b.value[0]});function K(p){!confirm("Are you sure to delete "+p.name+"?")||C.delete("/delivery-product-mappings/"+p.id)}function w(){C.get("/delivery-product-mappings",{...l.value,numberPerPage:l.value.numberPerPage.id},{preserveState:!0,replace:!0})}function N(){C.get("/delivery-product-mappings")}function k(p){l.value.sortKey=p,l.value.sortBy=!l.value.sortBy,w()}return(p,o)=>(d(),u(v,null,[t(m(T),{title:"Delivery Platform"}),t(I,null,{header:n(()=>[Z]),default:n(()=>{var V,P;return[e("div",q,[e("div",G,[e("div",H,[t(m(S),{href:"/delivery-product-mappings/create"},{default:n(()=>[t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"},{default:n(()=>[t(m(A),{class:"h-4 w-4","aria-hidden":"true"}),Q]),_:1})]),_:1})]),e("div",W,[t(L,{placeholderStr:"Name",modelValue:l.value.name,"onUpdate:modelValue":o[0]||(o[0]=s=>l.value.name=s)},{default:n(()=>[r(" Name ")]),_:1},8,["modelValue"]),t(L,{placeholderStr:"Vend ID",modelValue:l.value.vend_code,"onUpdate:modelValue":o[1]||(o[1]=s=>l.value.vend_code=s)},{default:n(()=>[r(" Vend ID ")]),_:1},8,["modelValue"])]),e("div",X,[e("div",Y,[e("div",M,[t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[2]||(o[2]=s=>w())},{default:n(()=>[t(m(D),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1}),t(_,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[3]||(o[3]=s=>N())},{default:n(()=>[t(m(E),{class:"h-4 w-4","aria-hidden":"true"}),te]),_:1})])]),e("div",se,[e("p",ne,[le,e("span",oe,i((V=a.deliveryProductMappings.meta.from)!=null?V:0),1),ae,e("span",re,i((P=a.deliveryProductMappings.meta.to)!=null?P:0),1),de,e("span",ie,i(a.deliveryProductMappings.meta.total),1),ue]),t(J,{modelValue:l.value.numberPerPage,"onUpdate:modelValue":o[4]||(o[4]=s=>l.value.numberPerPage=s),options:b.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:w},null,8,["modelValue","options"])])])]),e("div",ce,[e("div",me,[e("div",fe,[e("table",ge,[e("thead",pe,[e("tr",_e,[t(h,null,{default:n(()=>[r(" # ")]),_:1}),t(B,{modelName:"name",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:o[5]||(o[5]=s=>k("name"))},{default:n(()=>[r(" Name ")]),_:1},8,["sortKey","sortBy"]),t(B,{modelName:"operator_id",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:o[6]||(o[6]=s=>k("operator_id"))},{default:n(()=>[r(" Operator ")]),_:1},8,["sortKey","sortBy"]),t(B,{modelName:"delivery_platform_id",sortKey:l.value.sortKey,sortBy:l.value.sortBy,onSortTable:o[7]||(o[7]=s=>k("delivery_platform_id"))},{default:n(()=>[r(" Platform ")]),_:1},8,["sortKey","sortBy"]),t(h,null,{default:n(()=>[r(" Channel - Product ")]),_:1}),t(h,null,{default:n(()=>[r(" Binded Vending Machines ")]),_:1}),t(h)])]),e("tbody",xe,[(d(!0),u(v,null,$(a.deliveryProductMappings.data,(s,c)=>(d(),u("tr",{key:s.id,class:"divide-x divide-gray-200"},[t(g,{currentIndex:c,totalLength:a.deliveryProductMappings.length,inputClass:"text-center"},{default:n(()=>[r(i(a.deliveryProductMappings.meta.from+c),1)]),_:2},1032,["currentIndex","totalLength"]),t(g,{currentIndex:c,totalLength:a.deliveryProductMappings.length,inputClass:"text-left"},{default:n(()=>[r(i(s.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(g,{currentIndex:c,totalLength:a.deliveryProductMappings.length,inputClass:"text-left"},{default:n(()=>[r(i(s.operator?s.operator.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(g,{currentIndex:c,totalLength:a.deliveryProductMappings.length,inputClass:"text-center"},{default:n(()=>[r(i(s.deliveryPlatformOperator&&s.deliveryPlatformOperator.deliveryPlatform?s.deliveryPlatformOperator.deliveryPlatform.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(g,{currentIndex:c,totalLength:a.deliveryProductMappings.length,inputClass:"text-left"},{default:n(()=>[e("ul",he,[(d(!0),u(v,null,$(s.deliveryProductMappingItemsJson,f=>(d(),u("li",ve,[e("span",ye,i(f.channel_code),1),f.product.code?(d(),u("span",be,i(f.product.code),1)):y("",!0),e("span",null," - "+i(f.product.name),1)]))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(g,{currentIndex:c,totalLength:a.deliveryProductMappings.length,inputClass:"text-left"},{default:n(()=>[e("ul",we,[(d(!0),u(v,null,$(s.deliveryProductMappingVends,f=>(d(),u("li",ke,i(f.vend.full_name),1))),256))])]),_:2},1032,["currentIndex","totalLength"]),t(g,{currentIndex:c,totalLength:a.deliveryProductMappings.length,inputClass:"text-center"},{default:n(()=>[e("div",Be,[t(m(S),{href:"/delivery-product-mappings/"+s.id+"/edit"},{default:n(()=>[t(_,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"},{default:n(()=>[t(m(R),{class:"w-4 h-4"}),$e]),_:1})]),_:2},1032,["href"]),t(_,{type:"button",class:U(["bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1",[s.vendsJson&&s.vendsJson.length>0?"opacity-50 cursor-not-allowed":""]]),onClick:f=>K(s),disabled:s.vendsJson&&s.vendsJson.length>0},{default:n(()=>[e("span",Ce,[t(m(z),{class:"w-4 h-4"}),Ve]),s.vendsJson&&s.vendsJson.length>0?(d(),u("span",Pe," (Binded) ")):y("",!0)]),_:2},1032,["class","onClick","disabled"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.deliveryProductMappings.data.length?y("",!0):(d(),u("tr",Le,Ke))])]),a.deliveryProductMappings.data.length?(d(),j(O,{key:0,links:a.deliveryProductMappings.links,meta:a.deliveryProductMappings.meta},null,8,["links","meta"])):y("",!0)])])])])]}),_:1})],64))}};export{He as default};
