import{_ as H}from"./Authenticated.1768e4a0.js";import{_ as C}from"./Button.cc048bf1.js";import J from"./Form.1e0f46f5.js";import{_ as Q}from"./Paginator.5428c23d.js";import{_ as D,r as W}from"./SearchInput.a0506ae4.js";import{_ as f}from"./MultiSelect.338fea91.js";import{_ as g,a as i}from"./TableData.f6f423a1.js";import{_ as y}from"./TableHeadSort.d3cdd988.js";import{g as c,h as X,f as h,a as e,u as _,w as a,F as w,o as x,Z as Y,b as l,d as r,t as m,k as G,l as P,c as M,O as z}from"./app.160edf4c.js";import{r as ee}from"./PlusIcon.1f5c8b93.js";import{r as te}from"./BackspaceIcon.fc42be6f.js";import{r as le}from"./PencilSquareIcon.2e8fde76.js";import"./open-closed.9b5fc110.js";import"./use-resolve-button-type.35c00338.js";import"./RectangleStackIcon.14f53edf.js";import"./_plugin-vue_export-helper.cdc0426e.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.a2b03457.js";const ae=l("h2",{class:"font-semibold text-xl text-gray-800 leading-tight"}," Customers ",-1),ne={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},oe={class:"-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3"},se={class:"flex justify-end"},re=l("span",null," Create ",-1),de={class:"grid grid-cols-1 md:grid-cols-5 gap-2"},ue=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category ",-1),ie=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Category Group ",-1),me=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Status ",-1),ce=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Account Manager ",-1),ge=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Zone ",-1),pe=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Price Template ",-1),fe=l("label",{for:"text",class:"block text-sm font-medium text-gray-700"}," Tags ",-1),xe={class:"flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5"},ve={class:"mt-3"},ye={class:"flex space-x-1"},he=l("span",null," Search ",-1),be=l("span",null," Reset ",-1),_e={class:"flex flex-col space-y-2"},Ce={class:"text-sm text-gray-700 leading-5 flex space-x-1"},Be=l("span",null,"Showing",-1),Le={class:"font-medium"},ke=l("span",null,"to",-1),Ve={class:"font-medium"},Ie=l("span",null,"of",-1),we={class:"font-medium"},Pe=l("span",null,"results",-1),Se={class:"mt-6 flex flex-col"},$e={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},Ke={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll"},Oe={class:"min-w-full border-separate",style:{"border-spacing":"0"}},Ne={class:"bg-gray-100"},Te={class:"divide-x divide-gray-200"},Ue={class:"bg-white"},je={class:"inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800"},Ae={class:"flex justify-center space-x-1"},De=l("span",null," Edit ",-1),Ge={key:0},Me=l("td",{colspan:"24",class:"relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"}," No Results Found ",-1),ze=[Me],st={__name:"Index",props:{customers:Object,categories:Object,categoryGroups:Object,priceTemplates:Object,profiles:Object,statuses:Object,tags:Object,users:Object,zones:Object},setup(d){const p=d,n=c({name:"",statuses:[],sortKey:"",sortBy:!0,numberPerPage:100}),b=c(!1),B=c(),S=c([]),$=c([]),L=c([]),F=c([]),K=c([]),O=c([]),N=c([]),T=c([]),k=c(""),V=c([]);X(()=>{V.value=[{id:100,value:100},{id:200,value:200},{id:500,value:500},{id:"All",value:"All"}],n.value.numberPerPage=V.value[0],S.value=p.categories.data.map(s=>({id:s.id,name:s.name})),$.value=p.categoryGroups.data.map(s=>({id:s.id,name:s.name})),L.value=p.priceTemplates.data.map(s=>({id:s.id,name:s.name})),F.value=p.profiles.data.map(s=>({id:s.id,name:s.name})),K.value=p.statuses.data.map(s=>({id:s.id,name:s.name})),N.value=p.users.data.map(s=>({id:s.id,name:s.name})),T.value=p.zones.data.map(s=>({id:s.id,name:s.name})),L.value=p.priceTemplates.data.map(s=>({id:s.id,name:s.name})),O.value=p.tags.data.map(s=>({id:s.id,name:s.name}))});function E(){k.value="create",B.value=null,b.value=!0}function Z(s){k.value="update",B.value=s,b.value=!0}function I(){z.get("/customers",{...n.value,statuses:n.value.statuses.map(s=>s.id),numberPerPage:n.value.numberPerPage.id},{preserveState:!0,replace:!0})}function R(){z.get("/customers")}function v(s){n.value.sortKey=s,n.value.sortBy=!n.value.sortBy,I()}function q(){b.value=!1}return(s,o)=>(x(),h(w,null,[e(_(Y),{title:"Customers"}),e(H,null,{header:a(()=>[ae]),default:a(()=>{var U,j;return[l("div",ne,[l("div",oe,[l("div",se,[e(C,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[0]||(o[0]=t=>E())},{default:a(()=>[e(_(ee),{class:"h-4 w-4","aria-hidden":"true"}),re]),_:1})]),l("div",de,[e(D,{placeholderStr:"ID",modelValue:n.value.code,"onUpdate:modelValue":o[1]||(o[1]=t=>n.value.code=t)},{default:a(()=>[r(" ID ")]),_:1},8,["modelValue"]),e(D,{placeholderStr:"Name",modelValue:n.value.name,"onUpdate:modelValue":o[2]||(o[2]=t=>n.value.name=t)},{default:a(()=>[r(" ID Name ")]),_:1},8,["modelValue"]),l("div",null,[ue,e(f,{modelValue:n.value.categories,"onUpdate:modelValue":o[3]||(o[3]=t=>n.value.categories=t),options:S.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[ie,e(f,{modelValue:n.value.categoryGroups,"onUpdate:modelValue":o[4]||(o[4]=t=>n.value.categoryGroups=t),options:$.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[me,e(f,{modelValue:n.value.statuses,"onUpdate:modelValue":o[5]||(o[5]=t=>n.value.statuses=t),options:K.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[ce,e(f,{modelValue:n.value.handled_by,"onUpdate:modelValue":o[6]||(o[6]=t=>n.value.handled_by=t),options:N.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[ge,e(f,{modelValue:n.value.zone_id,"onUpdate:modelValue":o[7]||(o[7]=t=>n.value.zone_id=t),options:T.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[pe,e(f,{modelValue:n.value.price_template_id,"onUpdate:modelValue":o[8]||(o[8]=t=>n.value.price_template_id=t),options:L.value,trackBy:"id",valueProp:"id",label:"name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),l("div",null,[fe,e(f,{modelValue:n.value.tags,"onUpdate:modelValue":o[9]||(o[9]=t=>n.value.tags=t),options:O.value,trackBy:"id",valueProp:"id",label:"name",mode:"tags",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])]),l("div",xe,[l("div",ve,[l("div",ye,[e(C,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[10]||(o[10]=t=>I())},{default:a(()=>[e(_(W),{class:"h-4 w-4","aria-hidden":"true"}),he]),_:1}),e(C,{class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:o[11]||(o[11]=t=>R())},{default:a(()=>[e(_(te),{class:"h-4 w-4","aria-hidden":"true"}),be]),_:1})])]),l("div",_e,[l("p",Ce,[Be,l("span",Le,m((U=d.customers.meta.from)!=null?U:0),1),ke,l("span",Ve,m((j=d.customers.meta.to)!=null?j:0),1),Ie,l("span",we,m(d.customers.meta.total),1),Pe]),e(f,{modelValue:n.value.numberPerPage,"onUpdate:modelValue":o[12]||(o[12]=t=>n.value.numberPerPage=t),options:V.value,trackBy:"id",valueProp:"id",label:"value",placeholder:"Select","open-direction":"bottom",class:"mt-1",onSelected:I},null,8,["modelValue","options"])])])]),l("div",Se,[l("div",$e,[l("div",Ke,[l("table",Oe,[l("thead",Ne,[l("tr",Te,[e(g,null,{default:a(()=>[r(" # ")]),_:1}),e(y,{modelName:"code",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[13]||(o[13]=t=>v("code"))},{default:a(()=>[r(" ID ")]),_:1},8,["sortKey","sortBy"]),e(y,{modelName:"name",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[14]||(o[14]=t=>v("name"))},{default:a(()=>[r(" ID Name ")]),_:1},8,["sortKey","sortBy"]),e(y,{modelName:"first_transaction_id",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[15]||(o[15]=t=>v("first_transaction_id"))},{default:a(()=>[r(" First Inv Date ")]),_:1},8,["sortKey","sortBy"]),e(y,{modelName:"category_id",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[16]||(o[16]=t=>v("category_id"))},{default:a(()=>[r(" Category ")]),_:1},8,["sortKey","sortBy"]),e(y,{modelName:"category_group",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[17]||(o[17]=t=>v("category_group"))},{default:a(()=>[r(" Group ")]),_:1},8,["sortKey","sortBy"]),e(y,{modelName:"handled_by",sortKey:n.value.sortKey,sortBy:n.value.sortBy,onSortTable:o[18]||(o[18]=t=>v("handled_by"))},{default:a(()=>[r(" Acc Manager ")]),_:1},8,["sortKey","sortBy"]),e(g,null,{default:a(()=>[r(" Attn Name ")]),_:1}),e(g,null,{default:a(()=>[r(" Contact ")]),_:1}),e(g,null,{default:a(()=>[r(" Del Address ")]),_:1}),e(g,null,{default:a(()=>[r(" Del Postcode ")]),_:1}),e(g,null,{default:a(()=>[r(" Tags ")]),_:1}),e(g,null,{default:a(()=>[r(" Zone ")]),_:1}),e(g,null,{default:a(()=>[r(" Updated At ")]),_:1}),e(g,null,{default:a(()=>[r(" Updated By ")]),_:1}),e(g,null,{default:a(()=>[r(" Created At ")]),_:1}),e(g,null,{default:a(()=>[r(" Created By ")]),_:1}),e(g,null,{default:a(()=>[r(" Status ")]),_:1})])]),l("tbody",Ue,[(x(!0),h(w,null,G(d.customers.data,(t,u)=>(x(),h("tr",{key:t.id,class:"divide-x divide-gray-200"},[e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(d.customers.meta.from+u),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(t.code),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-left"},{default:a(()=>[r(m(t.name),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(t.category.name),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(t.category.categoryGroup.name),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},null,8,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-left"},{default:a(()=>[r(m(t.deliveryAddress?t.deliveryAddress.full_address:null),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(t.deliveryAddress?t.deliveryAddress.postcode:null),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-left"},{default:a(()=>[(x(!0),h(w,null,G(d.tags,A=>(x(),h("span",je,m(A.name),1))),256))]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(t.zone?t.zone.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(t.updatedBy?t.updatedBy.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(t.updated_at),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(t.createdBy?t.createdBy.name:null),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(t.created_at),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[r(m(t.status?t.status.name:""),1)]),_:2},1032,["currentIndex","totalLength"]),e(i,{currentIndex:u,totalLength:d.customers.length,inputClass:"text-center"},{default:a(()=>[l("div",Ae,[e(C,{type:"button",class:"bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1",onClick:A=>Z(t)},{default:a(()=>[e(_(le),{class:"w-4 h-4"}),De]),_:2},1032,["onClick"])])]),_:2},1032,["currentIndex","totalLength"])]))),128)),d.customers.data.length?P("",!0):(x(),h("tr",Ge,ze))])]),d.customers.data.length?(x(),M(Q,{key:0,links:d.customers.links,meta:d.customers.meta},null,8,["links","meta"])):P("",!0)])])])]),b.value?(x(),M(J,{key:0,customer:B.value,type:k.value,showModal:b.value,onModalClose:q},null,8,["customer","type","showModal"])):P("",!0)]}),_:1})],64))}};export{st as default};