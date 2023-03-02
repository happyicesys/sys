import{_ as J}from"./Authenticated.a81153b4.js";import{_ as C}from"./Button.d03094fa.js";import q from"./Form.2a5c4009.js";import{_ as D,r as Q,T as g,a as W,b as i}from"./TableData.1303af7a.js";import{_ as f}from"./MultiSelect.abaf8b0b.js";import{_ as v}from"./TableHeadSort.0b18be27.js";import{i as m,j as X,o as x,g as y,a as e,b,w as n,F as w,H as Y,d as l,t as c,m as G,p as P,c as M,f as r,J as z}from"./app.a148a0ce.js";import{r as ee}from"./PlusIcon.f9159983.js";import{r as te}from"./BackspaceIcon.5a40654b.js";import{r as le}from"./PencilSquareIcon.e9149ddd.js";import"./open-closed.ce385b24.js";import"./use-resolve-button-type.d397d356.js";import"./RectangleStackIcon.4f67b99f.js";import"./_plugin-vue_export-helper.cdc0426e.js";const ne=l("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Customers ",-1),ae={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},se={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},oe={class:"flex justify-end"},re=l("span",null," Create ",-1),de={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ue=r(" ID "),ie=r(" ID Name "),ce=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),me=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category Group ",-1),ge=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Status ",-1),pe=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Account Manager ",-1),fe=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Zone ",-1),xe=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Price Template ",-1),he=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Tags ",-1),ve={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ye={class:"mt-3"},_e={class:"flex space-x-1"},be=l("span",null," Search ",-1),Ce=l("span",null," Reset ",-1),Be={class:"flex flex-col space-y-2"},Le={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Ve=l("span",null,"Showing",-1),ke={class:"font-medium"},Ie=l("span",null,"to",-1),we={class:"font-medium"},Pe=l("span",null,"of",-1),Se={class:"font-medium"},$e=l("span",null,"results",-1),Ke={class:"mt-6 flex flex-col"},Oe={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Te={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Ne={class:"min-w-full border-separate",style:{"border-spacing":"0"}},je={class:"bg-gray-100"},Ue={class:"divide-x divide-gray-200"},Ae=r(" # "),De=r(" ID "),Ge=r(" ID Name "),Me=r(" First Inv Date "),ze=r(" Category "),Fe=r(" Group "),Ee=r(" Acc Manager "),He=r(" Attn Name "),Re=r(" Contact "),Ze=r(" Del Address "),Je=r(" Del Postcode "),qe=r(" Tags "),Qe=r(" Zone "),We=r(" Updated At "),Xe=r(" Updated By "),Ye=r(" Created At "),et=r(" Created By "),tt=r(" Status "),lt={class:"bg-white"},nt={class:"inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800"},at={class:"flex justify-center space-x-1"},st=l("span",null," Edit ",-1),ot={key:0},rt=l("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),dt=[rt],Bt={__name:"Index",props:{customers:Object,categories:Object,categoryGroups:Object,priceTemplates:Object,profiles:Object,statuses:Object,tags:Object,users:Object,zones:Object},setup(d){const p=d,a=m({name:"",statuses:[],sortKey:"",sortBy:!0,numberPerPage:100}),_=m(!1),B=m(),S=m([]),$=m([]),L=m([]),F=m([]),K=m([]),O=m([]),T=m([]),N=m([]),V=m(""),k=m([]);X(()=>{k.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],a.value.numberPerPage=k.value[0],S.value=p.categories.data.map(o=>({id:o.id,name:o.name})),$.value=p.categoryGroups.data.map(o=>({id:o.id,name:o.name})),L.value=p.priceTemplates.data.map(o=>({id:o.id,name:o.name})),F.value=p.profiles.data.map(o=>({id:o.id,name:o.name})),K.value=p.statuses.data.map(o=>({id:o.id,name:o.name})),T.value=p.users.data.map(o=>({id:o.id,name:o.name})),N.value=p.zones.data.map(o=>({id:o.id,name:o.name})),L.value=p.priceTemplates.data.map(o=>({id:o.id,name:o.name})),O.value=p.tags.data.map(o=>({id:o.id,name:o.name}))});function E(){V.value="create",B.value=null,_.value=!0}function H(o){V.value="update",B.value=o,_.value=!0}function I(){z.Inertia.get("/customers",{...a.value,statuses:a.value.statuses.map(o=>o.id),numberPerPage:a.value.numberPerPage.id},{preserveState:!0,replace:!0})}function R(){z.Inertia.get("/customers")}function h(o){a.value.sortKey=o,a.value.sortBy=!a.value.sortBy,I()}function Z(){_.value=!1}return(o,s)=>(x(),y(w,null,[e(b(Y),{title:"Customers"}),e(J,null,{header:n(()=>[ne]),default:n(()=>{var j,U;return[l("div",ae,[l("div",se,[l("div",oe,[e(C,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[0]||(s[0]=t=>E())},{default:n(()=>[e(b(ee),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})]),l("div",de,[e(D,{placeholderStr:"ID",modelValue:a.value.code,"onUpdate:modelValue":s[1]||(s[1]=t=>a.value.code=t)},{default:n(()=>[ue]),_:1},8,["modelValue"]),e(D,{placeholderStr:"Name",modelValue:a.value.name,"onUpdate:modelValue":s[2]||(s[2]=t=>a.value.name=t)},{default:n(()=>[ie]),_:1},8,["modelValue"]),l("div",null,[ce,e(f,{modelValue:a.value.categories,"onUpdate:modelValue":s[3]||(s[3]=t=>a.value.categories=t),options:S.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[me,e(f,{modelValue:a.value.categoryGroups,"onUpdate:modelValue":s[4]||(s[4]=t=>a.value.categoryGroups=t),options:$.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[ge,e(f,{modelValue:a.value.statuses,"onUpdate:modelValue":s[5]||(s[5]=t=>a.value.statuses=t),options:K.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[pe,e(f,{modelValue:a.value.handled_by,"onUpdate:modelValue":s[6]||(s[6]=t=>a.value.handled_by=t),options:T.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[fe,e(f,{modelValue:a.value.zone_id,"onUpdate:modelValue":s[7]||(s[7]=t=>a.value.zone_id=t),options:N.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[xe,e(f,{modelValue:a.value.price_template_id,"onUpdate:modelValue":s[8]||(s[8]=t=>a.value.price_template_id=t),options:L.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[he,e(f,{modelValue:a.value.tags,"onUpdate:modelValue":s[9]||(s[9]=t=>a.value.tags=t),options:O.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),l("div",ve,[l("div",ye,[l("div",_e,[e(C,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[10]||(s[10]=t=>I())},{default:n(()=>[e(b(Q),{class:"h-4 w-4","aria-hidden":"true"}),be]),_:1}),e(C,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:s[11]||(s[11]=t=>R())},{default:n(()=>[e(b(te),{class:"h-4 w-4","aria-hidden":"true"}),Ce]),_:1})])]),l("div",Be,[l("p",Le,[Ve,l("span",ke,c((j=d.customers.meta.from)!=null?j:0),1),Ie,l("span",we,c((U=d.customers.meta.to)!=null?U:0),1),Pe,l("span",Se,c(d.customers.meta.total),1),$e]),e(f,{modelValue:a.value.numberPerPage,"onUpdate:modelValue":s[12]||(s[12]=t=>a.value.numberPerPage=t),options:k.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:I},null,8,["modelValue","options"])])])]),l("div",Ke,[l("div",Oe,[l("div",Te,[l("table",Ne,[l("thead",je,[l("tr",Ue,[e(g,null,{default:n(()=>[Ae]),_:1}),e(v,{modelName:"code",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[13]||(s[13]=t=>h("code"))},{default:n(()=>[De]),_:1},8,["sortKey","sortBy"]),e(v,{modelName:"name",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[14]||(s[14]=t=>h("name"))},{default:n(()=>[Ge]),_:1},8,["sortKey","sortBy"]),e(v,{modelName:"first_transaction_id",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[15]||(s[15]=t=>h("first_transaction_id"))},{default:n(()=>[Me]),_:1},8,["sortKey","sortBy"]),e(v,{modelName:"category_id",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[16]||(s[16]=t=>h("category_id"))},{default:n(()=>[ze]),_:1},8,["sortKey","sortBy"]),e(v,{modelName:"category_group",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[17]||(s[17]=t=>h("category_group"))},{default:n(()=>[Fe]),_:1},8,["sortKey","sortBy"]),e(v,{modelName:"handled_by",sortKey:a.value.sortKey,sortBy:a.value.sortBy,onSortTable:s[18]||(s[18]=t=>h("handled_by"))},{default:n(()=>[Ee]),_:1},8,["sortKey","sortBy"]),e(g,null,{default:n(()=>[He]),_:1}),e(g,null,{default:n(()=>[Re]),_:1}),e(g,null,{default:n(()=>[Ze]),_:1}),e(g,null,{default:n(()=>[Je]),_:1}),e(g,null,{default:n(()=>[qe]),_:1}),e(g,null,{default:n(()=>[Qe]),_:1}),e(g,null,{default:n(()=>[We]),_:1}),e(g,null,{default:n(()=>[Xe]),_:1}),e(g,null,{default:n(()=>[Ye]),_:1}),e(g,null,{default:n(()=>[et]),_:1}),e(g,null,{default:n(()=>[tt]),_:1})])]),l("tbody",lt,[(x(!0),y(w,null,G(d.customers.data,(t,u)=>(x(),y("tr",{key:t.id,class:"divide-x divide-gray-200"},[e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(d.customers.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(t.code),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-left"},{default:n(()=>[r(c(t.name),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(t.category.name),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(t.category.categoryGroup.name),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-left"},{default:n(()=>[r(c(t.deliveryAddress?t.deliveryAddress.full_address:null),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(t.deliveryAddress?t.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-left"},{default:n(()=>[(x(!0),y(w,null,G(d.tags,A=>(x(),y("span",nt,c(A.name),1))),256))]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(t.zone?t.zone.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(t.updatedBy?t.updatedBy.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(t.updated_at),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(t.createdBy?t.createdBy.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(t.created_at),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[r(c(t.status?t.status.name:""),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:n(()=>[l("div",at,[e(C,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:A=>H(t)},{default:n(()=>[e(b(le),{class:"w-4 h-4"}),st]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.customers.data.length?P("",!0):(x(),y("tr",ot,dt))])]),d.customers.data.length?(x(),M(W,{key:0,links:d.customers.links,meta:d.customers.meta},null,8,["links","meta"])):P("",!0)])])])]),_.value?(x(),M(q,{key:0,customer:B.value,type:V.value,showModal:_.value,onModalClose:Z},null,8,["customer","type","showModal"])):P("",!0)]}),_:1})],64))}};export{Bt as default};
