import{_ as M}from"./Authenticated.c60e44a2.js";import{_ as x}from"./Button.a4bc7dc6.js";import G from"./Form.7582506a.js";import{_ as B,r as E,a as H,T as c,b as $,c as R,d as u}from"./TableHeadSort.31b87d59.js";import{_ as y}from"./MultiSelect.ab19e96f.js";import{i as m,j as D,o as f,g as _,a as t,b as h,w as l,F as N,H as J,d as e,t as d,m as Y,p as b,c as O,f as i,J as j}from"./app.7e392996.js";import{r as q,a as z}from"./PlusIcon.f1ba9af9.js";import"./open-closed.dc611207.js";import"./use-resolve-button-type.3a66aace.js";import"./RectangleStackIcon.7b70b060.js";import"./Uom.2a0ae4e8.js";import"./FormInput.c9fc7f3a.js";import"./Modal.7bb3d2e4.js";import"./ArrowUturnLeftIcon.52ddfe23.js";import"./CheckCircleIcon.e8e6b0bc.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.1e831869.js";import"./FormTextarea.b8aee73a.js";import"./_plugin-vue_export-helper.cdc0426e.js";const Q=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Product (List) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end mb-4"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},oe=i(" Code "),se=i(" Name "),le=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Inventory? ",-1),ae=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Active? ",-1),ne=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Comm or SF? ",-1),ie={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},re={class:"mt-3"},de={class:"flex space-x-1"},ue=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),me={class:"flex flex-col space-y-2"},fe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=e("span",null,"Showing",-1),ve={class:"font-medium"},_e=e("span",null,"to",-1),he={class:"font-medium"},pe=e("span",null,"of",-1),xe={class:"font-medium"},ye=e("span",null,"results",-1),be={class:"mt-6 flex flex-col"},Ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},we={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ve={class:"bg-gray-100"},Ie={class:"divide-x divide-gray-200"},Se=i(" # "),Le=i(" Code "),Pe=i(" Name "),Be=i(" Thumbnail "),$e=i(" Category "),Ne=i(" Group "),Oe=i(" Is Inventory "),je=i(" Is Active "),Fe={class:"bg-white"},Ke={class:"flex justify-center"},Te=["src"],Ue={class:"flex justify-center space-x-1"},Ae=e("span",null," Edit ",-1),Me={key:0},Ge=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ee=[Ge],rt={__name:"Index",props:{categories:Object,categoryGroups:Object,products:Object,uoms:Object},setup(n){const s=m({code:"",name:"",is_active:"",is_comm_or_sf:"",is_inventory:"",sortKey:"",sortBy:!0,numberPerPage:100}),g=m([]),C=m([]),v=m(!1),k=m(),w=m(""),V=m([]);D(()=>{V.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=V.value[0],g.value=[{id:1,value:"Yes"},{id:0,value:"No"}],s.value.is_active=g.value[0],s.value.is_inventory=g.value[0],C.value=[{id:"",value:"All"},{id:"comm",value:"Comm Only"},{id:"sf",value:"SF Only"},{id:"both",value:"Both Comm & SF"}],s.value.is_comm_or_sf=C.value[0]});function F(){w.value="create",k.value=null,v.value=!0}function K(p){w.value="update",k.value=p,v.value=!0}function T(){}function I(){j.Inertia.get("/products",{...s.value,numberPerPage:s.value.numberPerPage.id,is_active:s.value.is_active.id,is_inventory:s.value.is_inventory.id,is_comm_or_sf:s.value.is_comm_or_sf.id},{preserveState:!0,replace:!0})}function U(){j.Inertia.get("/products")}function S(p){s.value.sortKey=p,s.value.sortBy=!s.value.sortBy,I()}function A(){v.value=!1}return(p,a)=>(f(),_(N,null,[t(h(J),{title:"Product"}),t(M,null,{header:l(()=>[Q]),default:l(()=>{var L,P;return[e("div",W,[e("div",X,[e("div",Z,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[0]||(a[0]=o=>F())},{default:l(()=>[t(h(q),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(B,{placeholderStr:"Code",modelValue:s.value.code,"onUpdate:modelValue":a[1]||(a[1]=o=>s.value.code=o)},{default:l(()=>[oe]),_:1},8,["modelValue"]),t(B,{placeholderStr:"Name",modelValue:s.value.name,"onUpdate:modelValue":a[2]||(a[2]=o=>s.value.name=o)},{default:l(()=>[se]),_:1},8,["modelValue"]),e("div",null,[le,t(y,{modelValue:s.value.is_inventory,"onUpdate:modelValue":a[3]||(a[3]=o=>s.value.is_inventory=o),options:g.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[ae,t(y,{modelValue:s.value.is_active,"onUpdate:modelValue":a[4]||(a[4]=o=>s.value.is_active=o),options:g.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[ne,t(y,{modelValue:s.value.is_comm_or_sf,"onUpdate:modelValue":a[5]||(a[5]=o=>s.value.is_comm_or_sf=o),options:C.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:T},null,8,["modelValue","options"])])]),e("div",ie,[e("div",re,[e("div",de,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[6]||(a[6]=o=>I())},{default:l(()=>[t(h(E),{class:"h-4 w-4","aria-hidden":"true"}),ue]),_:1}),t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:a[7]||(a[7]=o=>U())},{default:l(()=>[t(h(H),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",me,[e("p",fe,[ge,e("span",ve,d((L=n.products.meta.from)!=null?L:0),1),_e,e("span",he,d((P=n.products.meta.to)!=null?P:0),1),pe,e("span",xe,d(n.products.meta.total),1),ye]),t(y,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":a[8]||(a[8]=o=>s.value.numberPerPage=o),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:I},null,8,["modelValue","options"])])])]),e("div",be,[e("div",Ce,[e("div",ke,[e("table",we,[e("thead",Ve,[e("tr",Ie,[t(c,null,{default:l(()=>[Se]),_:1}),t($,{modelName:"code",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:a[9]||(a[9]=o=>S("code"))},{default:l(()=>[Le]),_:1},8,["sortKey","sortBy"]),t($,{modelName:"name",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:a[10]||(a[10]=o=>S("name"))},{default:l(()=>[Pe]),_:1},8,["sortKey","sortBy"]),t(c,null,{default:l(()=>[Be]),_:1}),t(c,null,{default:l(()=>[$e]),_:1}),t(c,null,{default:l(()=>[Ne]),_:1}),t(c,null,{default:l(()=>[Oe]),_:1}),t(c,null,{default:l(()=>[je]),_:1}),t(c)])]),e("tbody",Fe,[(f(!0),_(N,null,Y(n.products.data,(o,r)=>(f(),_("tr",{key:o.id,class:"divide-x divide-gray-200"},[t(u,{currentIndex:r,totalLength:n.products.length,inputClass:"text-center"},{default:l(()=>[i(d(n.products.meta.from+r),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:r,totalLength:n.products.length,inputClass:"text-center"},{default:l(()=>[i(d(o.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:r,totalLength:n.products.length,inputClass:"text-left"},{default:l(()=>[i(d(o.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:r,totalLength:n.products.length,inputClass:"text-center"},{default:l(()=>[e("div",Ke,[o.thumbnail?(f(),_("img",{key:0,class:"h-24 w-24 md:h-20 md:w-20 rounded-full",src:o.thumbnail.full_url,alt:""},null,8,Te)):b("",!0)])]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:r,totalLength:n.products.length,inputClass:"text-center"},{default:l(()=>[i(d(o.category_id?o.category_id.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:r,totalLength:n.products.length,inputClass:"text-center"},{default:l(()=>[i(d(o.category_group_id?o.category_group_id.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:r,totalLength:n.products.length,inputClass:"text-center"},{default:l(()=>[i(d(o.isInventory),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:r,totalLength:n.products.length,inputClass:"text-center"},{default:l(()=>[i(d(o.isActive),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:r,totalLength:n.products.length,inputClass:"text-center"},{default:l(()=>[e("div",Ue,[t(x,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:He=>K(o)},{default:l(()=>[t(h(z),{class:"w-4 h-4"}),Ae]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),n.products.data.length?b("",!0):(f(),_("tr",Me,Ee))])]),n.products.data.length?(f(),O(R,{key:0,links:n.products.links,meta:n.products.meta},null,8,["links","meta"])):b("",!0)])])])]),v.value?(f(),O(G,{key:0,product:k.value,categories:n.categories,categoryGroups:n.categoryGroups,uoms:n.uoms,type:w.value,showModal:v.value,onModalClose:A},null,8,["product","categories","categoryGroups","uoms","type","showModal"])):b("",!0)]}),_:1})],64))}};export{rt as default};
