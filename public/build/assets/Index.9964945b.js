import{_ as M}from"./Authenticated.a9654884.js";import{_ as x}from"./Button.bf7c4f05.js";import G from"./Form.940960ff.js";import{_ as B,r as E,a as H,T as c,b as $,c as R,d as u}from"./TableHeadSort.059d48eb.js";import{_ as y}from"./MultiSelect.5946c540.js";import{i as m,j as D,o as f,g as _,a as t,b as h,w as l,F as N,H as J,d as e,t as d,m as Y,p as b,c as O,f as r,J as j}from"./app.dc45475b.js";import{r as q}from"./PlusIcon.8da8a5c6.js";import{r as z}from"./PencilSquareIcon.f275b11f.js";import"./open-closed.21e6db5b.js";import"./use-resolve-button-type.3a144016.js";import"./RectangleStackIcon.b52de852.js";import"./Uom.a8c68f2c.js";import"./FormInput.c7ffb148.js";import"./Modal.cd08ed63.js";import"./ArrowUturnLeftIcon.f39898e6.js";import"./CheckCircleIcon.1843db13.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.80d2bc43.js";import"./FormTextarea.04342f1d.js";import"./_plugin-vue_export-helper.cdc0426e.js";const Q=e("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Product (List) ",-1),W={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},X={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},Z={class:"flex justify-end mb-4"},ee=e("span",null," Create ",-1),te={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},oe=r(" Code "),se=r(" Name "),le=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Inventory? ",-1),ne=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Active? ",-1),ae=e("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Is Comm or SF? ",-1),re={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ie={class:"mt-3"},de={class:"flex space-x-1"},ue=e("span",null," Search ",-1),ce=e("span",null," Reset ",-1),me={class:"flex flex-col space-y-2"},fe={class:"text-sm text-gray-700 leading-5 flex space-x-1"},ge=e("span",null,"Showing",-1),ve={class:"font-medium"},_e=e("span",null,"to",-1),he={class:"font-medium"},pe=e("span",null,"of",-1),xe={class:"font-medium"},ye=e("span",null,"results",-1),be={class:"mt-6 flex flex-col"},Ce={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},we={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ve={class:"bg-gray-100"},Ie={class:"divide-x divide-gray-200"},Se=r(" # "),Le=r(" Code "),Pe=r(" Name "),Be=r(" Thumbnail "),$e=r(" Category "),Ne=r(" Group "),Oe=r(" Is Inventory "),je=r(" Is Active "),Fe={class:"bg-white"},Ke={class:"flex justify-center"},Te=["src"],Ue={class:"flex justify-center space-x-1"},Ae=e("span",null," Edit ",-1),Me={key:0},Ge=e("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),Ee=[Ge],dt={__name:"Index",props:{categories:Object,categoryGroups:Object,products:Object,uoms:Object},setup(a){const s=m({code:"",name:"",is_active:"",is_comm_or_sf:"",is_inventory:"",sortKey:"",sortBy:!0,numberPerPage:100}),g=m([]),C=m([]),v=m(!1),k=m(),w=m(""),V=m([]);D(()=>{V.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],s.value.numberPerPage=V.value[0],g.value=[{id:1,value:"Yes"},{id:0,value:"No"}],s.value.is_active=g.value[0],s.value.is_inventory=g.value[0],C.value=[{id:"",value:"All"},{id:"comm",value:"Comm Only"},{id:"sf",value:"SF Only"},{id:"both",value:"Both Comm & SF"}],s.value.is_comm_or_sf=C.value[0]});function F(){w.value="create",k.value=null,v.value=!0}function K(p){w.value="update",k.value=p,v.value=!0}function T(){}function I(){j.Inertia.get("/products",{...s.value,numberPerPage:s.value.numberPerPage.id,is_active:s.value.is_active.id,is_inventory:s.value.is_inventory.id,is_comm_or_sf:s.value.is_comm_or_sf.id},{preserveState:!0,replace:!0})}function U(){j.Inertia.get("/products")}function S(p){s.value.sortKey=p,s.value.sortBy=!s.value.sortBy,I()}function A(){v.value=!1}return(p,n)=>(f(),_(N,null,[t(h(J),{title:"Product"}),t(M,null,{header:l(()=>[Q]),default:l(()=>{var L,P;return[e("div",W,[e("div",X,[e("div",Z,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[0]||(n[0]=o=>F())},{default:l(()=>[t(h(q),{class:"h-4 w-4","aria-hidden":"true"}),ee]),_:1})]),e("div",te,[t(B,{placeholderStr:"Code",modelValue:s.value.code,"onUpdate:modelValue":n[1]||(n[1]=o=>s.value.code=o)},{default:l(()=>[oe]),_:1},8,["modelValue"]),t(B,{placeholderStr:"Name",modelValue:s.value.name,"onUpdate:modelValue":n[2]||(n[2]=o=>s.value.name=o)},{default:l(()=>[se]),_:1},8,["modelValue"]),e("div",null,[le,t(y,{modelValue:s.value.is_inventory,"onUpdate:modelValue":n[3]||(n[3]=o=>s.value.is_inventory=o),options:g.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[ne,t(y,{modelValue:s.value.is_active,"onUpdate:modelValue":n[4]||(n[4]=o=>s.value.is_active=o),options:g.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),e("div",null,[ae,t(y,{modelValue:s.value.is_comm_or_sf,"onUpdate:modelValue":n[5]||(n[5]=o=>s.value.is_comm_or_sf=o),options:C.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:T},null,8,["modelValue","options"])])]),e("div",re,[e("div",ie,[e("div",de,[t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[6]||(n[6]=o=>I())},{default:l(()=>[t(h(E),{class:"h-4 w-4","aria-hidden":"true"}),ue]),_:1}),t(x,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:n[7]||(n[7]=o=>U())},{default:l(()=>[t(h(H),{class:"h-4 w-4","aria-hidden":"true"}),ce]),_:1})])]),e("div",me,[e("p",fe,[ge,e("span",ve,d((L=a.products.meta.from)!=null?L:0),1),_e,e("span",he,d((P=a.products.meta.to)!=null?P:0),1),pe,e("span",xe,d(a.products.meta.total),1),ye]),t(y,{modelValue:s.value.numberPerPage,"onUpdate:modelValue":n[8]||(n[8]=o=>s.value.numberPerPage=o),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:I},null,8,["modelValue","options"])])])]),e("div",be,[e("div",Ce,[e("div",ke,[e("table",we,[e("thead",Ve,[e("tr",Ie,[t(c,null,{default:l(()=>[Se]),_:1}),t($,{modelName:"code",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:n[9]||(n[9]=o=>S("code"))},{default:l(()=>[Le]),_:1},8,["sortKey","sortBy"]),t($,{modelName:"name",sortKey:s.value.sortKey,sortBy:s.value.sortBy,onSortTable:n[10]||(n[10]=o=>S("name"))},{default:l(()=>[Pe]),_:1},8,["sortKey","sortBy"]),t(c,null,{default:l(()=>[Be]),_:1}),t(c,null,{default:l(()=>[$e]),_:1}),t(c,null,{default:l(()=>[Ne]),_:1}),t(c,null,{default:l(()=>[Oe]),_:1}),t(c,null,{default:l(()=>[je]),_:1}),t(c)])]),e("tbody",Fe,[(f(!0),_(N,null,Y(a.products.data,(o,i)=>(f(),_("tr",{key:o.id,class:"divide-x divide-gray-200"},[t(u,{currentIndex:i,totalLength:a.products.length,inputClass:"text-center"},{default:l(()=>[r(d(a.products.meta.from+i),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:a.products.length,inputClass:"text-center"},{default:l(()=>[r(d(o.code),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:a.products.length,inputClass:"text-left"},{default:l(()=>[r(d(o.name),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:a.products.length,inputClass:"text-center"},{default:l(()=>[e("div",Ke,[o.thumbnail?(f(),_("img",{key:0,class:"h-24 w-24 md:h-20 md:w-20 rounded-full",src:o.thumbnail.full_url,alt:""},null,8,Te)):b("",!0)])]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:a.products.length,inputClass:"text-center"},{default:l(()=>[r(d(o.category_id?o.category_id.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:a.products.length,inputClass:"text-center"},{default:l(()=>[r(d(o.category_group_id?o.category_group_id.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:a.products.length,inputClass:"text-center"},{default:l(()=>[r(d(o.isInventory),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:a.products.length,inputClass:"text-center"},{default:l(()=>[r(d(o.isActive),1)]),_:2},1032,["currentIndex","totalLength"]),t(u,{currentIndex:i,totalLength:a.products.length,inputClass:"text-center"},{default:l(()=>[e("div",Ue,[t(x,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:He=>K(o)},{default:l(()=>[t(h(z),{class:"w-4 h-4"}),Ae]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),a.products.data.length?b("",!0):(f(),_("tr",Me,Ee))])]),a.products.data.length?(f(),O(R,{key:0,links:a.products.links,meta:a.products.meta},null,8,["links","meta"])):b("",!0)])])])]),v.value?(f(),O(G,{key:0,product:k.value,categories:a.categories,categoryGroups:a.categoryGroups,uoms:a.uoms,type:w.value,showModal:v.value,onModalClose:A},null,8,["product","categories","categoryGroups","uoms","type","showModal"])):b("",!0)]}),_:1})],64))}};export{dt as default};
